<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\SourceMatrix;

use Closure;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\CurlImpersonateFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PuppeteerStealthFetchHandler;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuit;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Tools\Canary\Env;
use JOOservices\CrawlerX\Tools\Canary\LoginCookiePolicy;
use JOOservices\CrawlerX\Tools\Canary\RequiredFields;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/canary/Env.php';
require_once __DIR__ . '/canary/LoginCookiePolicy.php';
require_once __DIR__ . '/canary/RequiredFields.php';

enum Verdict: string
{
    case Working = 'working';
    case Degraded = 'degraded';
    case Blocked = 'blocked';
}

final readonly class MatrixRow
{
    public function __construct(
        public string $site,
        public string $capability,
        public string $url,
        public ?string $method,
        public ?int $status,
        public bool $challenge,
        public int $fieldsParsed,
        public int $fieldsRequired,
        public Verdict $verdict,
        public ?string $reason,
        public ?CrawlErrorCode $errorCode = null,
    ) {
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'capability' => $this->capability,
            'url' => $this->url,
            'method' => $this->method,
            'status' => $this->status,
            'challenge' => $this->challenge,
            'fields_parsed' => $this->fieldsParsed,
            'fields_required' => $this->fieldsRequired,
            'verdict' => $this->verdict->value,
            'reason' => $this->reason,
            'error_code' => $this->errorCode?->value,
        ];
    }
}

final readonly class MatrixObservation
{
    public function __construct(
        public CrawlOutcomeDto $outcome,
        public ?FetchResultDto $fetch,
    ) {
    }
}

final class RequestPacer
{
    /** @var array<string, float> */
    private array $nextAllowedAt = [];

    /** @var Closure(): float */
    private readonly Closure $clock;

    /** @var Closure(int): void */
    private readonly Closure $sleeper;

    /**
     * @param Closure(): float|null $clock
     * @param Closure(int): void|null $sleeper
     */
    public function __construct(
        ?Closure $clock = null,
        ?Closure $sleeper = null,
        private readonly float $minimumGapSeconds = 5.0,
    ) {
        $this->clock = $clock ?? static fn(): float => microtime(true);
        $this->sleeper = $sleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    /** @param array<string, float>|null $settings */
    public function wait(string $host, ?array $settings): void
    {
        $host = strtolower(rtrim(trim($host), '.'));
        if ($host === '') {
            return;
        }

        $now = ($this->clock)();
        $nextAllowedAt = $this->nextAllowedAt[$host] ?? $now;
        $waitSeconds = max(0.0, $nextAllowedAt - $now);
        if ($waitSeconds > 0.0) {
            ($this->sleeper)((int) round($waitSeconds * 1_000_000));
        }

        $gap = max($this->minimumGapSeconds, self::manifestGap($settings));
        $this->nextAllowedAt[$host] = ($this->clock)() + $gap;
    }

    /** @param array<string, float>|null $settings */
    private static function manifestGap(?array $settings): float
    {
        if ($settings === null) {
            return 0.0;
        }

        $default = $settings['default_gap_seconds'] ?? 0.0;
        $minimum = max(0.0, $settings['min_gap_seconds'] ?? 0.0);
        $maximum = max($minimum, $settings['max_gap_seconds'] ?? max($minimum, $default));

        return min($maximum, max($minimum, $default));
    }
}

final class PolitenessGuard
{
    /** @var array<string, int> */
    private array $requests = [];

    /** @var array<string, int> */
    private array $requestLimits = [];

    public function __construct(
        private readonly RequestPacer $pacer,
        private readonly int $maxRequestsPerCapability = 3,
    ) {
    }

    /** @param array<string, float>|null $settings */
    public function beforeRequest(string $site, string $capability, string $url, ?array $settings): bool
    {
        $key = $site . "\0" . $capability;
        if (($this->requests[$key] ?? 0) >= ($this->requestLimits[$key] ?? $this->maxRequestsPerCapability)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $this->pacer->wait($host, $settings);
        $this->requests[$key] = ($this->requests[$key] ?? 0) + 1;

        return true;
    }

    public function reserveRetryRequest(string $site, string $capability): void
    {
        $this->requestLimits[$site . "\0" . $capability] = max(0, $this->maxRequestsPerCapability - 1);
    }

    public function allowRetryRequest(string $site, string $capability): bool
    {
        $key = $site . "\0" . $capability;
        if (($this->requests[$key] ?? 0) >= $this->maxRequestsPerCapability) {
            return false;
        }

        $this->requestLimits[$key] = $this->maxRequestsPerCapability;

        return true;
    }

    public function requestCount(string $site, string $capability): int
    {
        return $this->requests[$site . "\0" . $capability] ?? 0;
    }
}

final class MatrixFetchTrace
{
    /** @var list<FetchResultDto> */
    private array $results = [];

    public function record(FetchResultDto $result): void
    {
        $this->results[] = $result;
    }

    public function lastResult(): ?FetchResultDto
    {
        return $this->results === [] ? null : $this->results[array_key_last($this->results)];
    }
}

final class MatrixFetchMethodHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly FetchMethodHandler $inner,
        private readonly PolitenessGuard $guard,
        private readonly MatrixFetchTrace $trace,
        private readonly string $site,
        private readonly string $capability,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $this->inner->supports($method);
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        if (! $this->guard->beforeRequest($this->site, $this->capability, $url, $profile->defaultThrottle)) {
            return new FetchResultDto(
                ok: false,
                body: '',
                status: 0,
                methodUsed: $method,
                elapsedMs: 0,
                challengeDetected: false,
                error: 'request_budget_exhausted',
            );
        }

        $result = $this->inner->fetch($url, $profile, $method, $options);
        $this->trace->record($result);

        return $result;
    }
}

final class MatrixClassifier
{
    /**
     * @param list<string> $missingFields
     * @return array{Verdict, ?string}
     */
    public static function classify(
        ?FetchResultDto $fetch,
        ?CrawlErrorCode $errorCode,
        int $parsed,
        int $required,
        array $missingFields = [],
    ): array {
        if ($errorCode === CrawlErrorCode::Challenge || $fetch?->challengeDetected === true) {
            return [Verdict::Blocked, 'challenge'];
        }

        if ($errorCode === CrawlErrorCode::AuthRequired) {
            return [Verdict::Blocked, 'auth_required'];
        }

        if ($fetch?->status === 401) {
            return [Verdict::Blocked, 'http_401'];
        }

        if ($fetch?->status === 403) {
            return [Verdict::Blocked, 'http_403'];
        }

        if ($errorCode === CrawlErrorCode::Blocked) {
            return [Verdict::Blocked, $errorCode->value];
        }

        if ($errorCode === CrawlErrorCode::Timeout || self::isTimeout($fetch?->error)) {
            return [Verdict::Degraded, 'timeout'];
        }

        if ($errorCode === CrawlErrorCode::Network || self::isNetworkError($fetch?->error)) {
            return [Verdict::Degraded, 'network'];
        }

        if ($errorCode !== null) {
            return [Verdict::Degraded, $errorCode->value];
        }

        if ($missingFields !== [] || $parsed < $required) {
            return [Verdict::Degraded, $missingFields === [] ? 'required_fields_missing' : implode(', ', $missingFields)];
        }

        if ($fetch !== null && ! $fetch->ok) {
            return [Verdict::Degraded, 'fetch_failed'];
        }

        return [Verdict::Working, null];
    }

    public static function shouldRetry(?FetchResultDto $fetch, ?CrawlErrorCode $errorCode): bool
    {
        if (
            $errorCode === CrawlErrorCode::Challenge
            || $errorCode === CrawlErrorCode::AuthRequired
            || $errorCode === CrawlErrorCode::Blocked
            || $fetch?->challengeDetected === true
            || in_array($fetch?->status, [401, 403], true)
        ) {
            return false;
        }

        return in_array($errorCode, [CrawlErrorCode::Timeout, CrawlErrorCode::Network], true)
            || self::isTimeout($fetch?->error)
            || self::isNetworkError($fetch?->error);
    }

    private static function isTimeout(?string $error): bool
    {
        if ($error === null) {
            return false;
        }

        $error = strtolower($error);

        return str_contains($error, 'timeout') || str_contains($error, 'timed out');
    }

    private static function isNetworkError(?string $error): bool
    {
        if ($error === null) {
            return false;
        }

        $error = strtolower($error);
        foreach ([
            'network',
            'connection refused',
            'connection reset',
            'could not resolve',
            'name or service not known',
            'temporary failure',
        ] as $marker) {
            if (str_contains($error, $marker)) {
                return true;
            }
        }

        return false;
    }
}

final class SourceMatrixRunner
{
    /** @var Closure(AdapterManifestDto, FixtureSampleDto, CrawlType): MatrixObservation */
    private readonly Closure $probe;

    private readonly ?PolitenessGuard $guard;

    /** @var Closure(int): void */
    private readonly Closure $retrySleeper;

    /**
     * @param callable(AdapterManifestDto, FixtureSampleDto, CrawlType): MatrixObservation $probe
     * @param callable(int): void|null $retrySleeper
     */
    public function __construct(callable $probe, ?PolitenessGuard $guard = null, ?callable $retrySleeper = null)
    {
        $this->probe = Closure::fromCallable($probe);
        $this->guard = $guard;
        $this->retrySleeper = Closure::fromCallable($retrySleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        });
    }

    /**
     * @param array<string, AdapterManifestDto> $manifests
     * @return list<MatrixRow>
     */
    public function run(array $manifests, ?string $siteFilter = null): array
    {
        ksort($manifests);
        $rows = [];

        foreach ($manifests as $manifest) {
            if ($siteFilter !== null && $manifest->slug !== $siteFilter) {
                continue;
            }

            $capabilities = $manifest->capabilities;
            sort($capabilities);
            foreach ($capabilities as $capability) {
                $type = CrawlType::tryFrom($capability);
                $sample = $this->sampleFor($manifest, $capability);
                if ($type === null || $sample === null) {
                    $rows[] = new MatrixRow(
                        site: $manifest->slug,
                        capability: $capability,
                        url: $sample?->url ?? '',
                        method: null,
                        status: null,
                        challenge: false,
                        fieldsParsed: 0,
                        fieldsRequired: self::requiredCount($capability),
                        verdict: Verdict::Degraded,
                        reason: $sample === null ? 'missing_sample_url' : 'unsupported_capability',
                        errorCode: $type === null ? CrawlErrorCode::UnsupportedUrl : CrawlErrorCode::Unknown,
                    );
                    continue;
                }

                if (LoginCookiePolicy::isRequired($manifest->slug)) {
                    $rows[] = self::failureRow(
                        $manifest->slug,
                        $capability,
                        $sample->url,
                        CrawlErrorCode::AuthRequired,
                    );
                    continue;
                }

                $this->guard?->reserveRetryRequest($manifest->slug, $capability);
                $observation = ($this->probe)($manifest, $sample, $type);
                $errorCode = $observation->outcome->error?->code;
                if (
                    MatrixClassifier::shouldRetry($observation->fetch, $errorCode)
                    && ($this->guard?->allowRetryRequest($manifest->slug, $capability) ?? true)
                ) {
                    ($this->retrySleeper)(15_000_000);
                    $observation = ($this->probe)($manifest, $sample, $type);
                }

                $result = $observation->outcome->list ?? $observation->outcome->item;
                $coverage = $result instanceof CrawlListResultDto || $result instanceof CrawlItemResultDto
                    ? RequiredFields::check($result, $capability)
                    : ['missing' => [], 'found' => [], 'expected' => []];
                $required = $coverage['expected'] === []
                    ? self::requiredCount($capability)
                    : count($coverage['expected']);
                $parsed = count($coverage['found']);
                $errorCode = $observation->outcome->error?->code;
                [$verdict, $reason] = MatrixClassifier::classify(
                    $observation->fetch,
                    $errorCode,
                    $parsed,
                    $required,
                    $coverage['missing'],
                );

                $rows[] = new MatrixRow(
                    site: $manifest->slug,
                    capability: $capability,
                    url: $sample->url,
                    method: $observation->fetch?->methodUsed->value,
                    status: $observation->fetch?->status,
                    challenge: $observation->fetch?->challengeDetected === true || $errorCode === CrawlErrorCode::Challenge,
                    fieldsParsed: $parsed,
                    fieldsRequired: $required,
                    verdict: $verdict,
                    reason: $reason,
                    errorCode: $errorCode,
                );
            }
        }

        usort($rows, static fn(MatrixRow $left, MatrixRow $right): int => [$left->site, $left->capability] <=> [$right->site, $right->capability]);

        return $rows;
    }

    private function sampleFor(AdapterManifestDto $manifest, string $capability): ?FixtureSampleDto
    {
        foreach ($manifest->fixtureSamples as $sample) {
            if ($sample->type === $capability) {
                return $sample;
            }
        }

        return null;
    }

    private static function failureRow(string $site, string $capability, string $url, CrawlErrorCode $code): MatrixRow
    {
        [$verdict, $reason] = MatrixClassifier::classify(null, $code, 0, self::requiredCount($capability));

        return new MatrixRow(
            site: $site,
            capability: $capability,
            url: $url,
            method: null,
            status: null,
            challenge: false,
            fieldsParsed: 0,
            fieldsRequired: self::requiredCount($capability),
            verdict: $verdict,
            reason: $reason,
            errorCode: $code,
        );
    }

    private static function requiredCount(string $capability): int
    {
        return match ($capability) {
            'listing', 'search' => 3,
            'detail' => 2,
            'performer_listing', 'performer_detail', 'performer_search' => 3,
            'gallery' => 2,
            default => 0,
        };
    }
}

final class SourceMatrixReport
{
    /** @param list<MatrixRow> $rows
     *  @return array<string, mixed>
     */
    public static function summary(array $rows, int $expectedSources): array
    {
        $sources = [];
        $verdicts = [
            Verdict::Working->value => 0,
            Verdict::Degraded->value => 0,
            Verdict::Blocked->value => 0,
        ];
        $errors = [];

        foreach ($rows as $row) {
            $sources[$row->site] = true;
            ++$verdicts[$row->verdict->value];
            $error = $row->errorCode?->value ?? 'none';
            $errors[$error] = ($errors[$error] ?? 0) + 1;
        }

        ksort($errors);
        $coveredSources = count($sources);

        return [
            'sources_covered' => $coveredSources,
            'sources_expected' => $expectedSources,
            'source_coverage' => $coveredSources . '/' . $expectedSources,
            'all_sources_covered' => $coveredSources === $expectedSources,
            'rows' => count($rows),
            'by_verdict' => $verdicts,
            'by_error_code' => $errors,
        ];
    }

    /** @param list<MatrixRow> $rows
     *  @param array<string, mixed> $summary
     */
    public static function markdown(array $rows, array $summary, ?array $runMetadata = null): string
    {
        $lines = [
            '# Source matrix',
            '',
        ];

        if ($runMetadata !== null) {
            $lines[] = '## Run conditions';
            $lines[] = '';
            $lines[] = '- Started (UTC): ' . ($runMetadata['started_at_utc'] ?? 'unavailable');
            $lines[] = '- Ended (UTC): ' . ($runMetadata['ended_at_utc'] ?? 'pending post-run capture');
            $lines[] = '- Tool git SHA: `' . ($runMetadata['tool_git_sha'] ?? 'unavailable') . '`';
            $lines[] = '- Host CPU count: ' . ($runMetadata['host_cpu_count'] ?? 'unavailable');
            $lines[] = '- Host load average at start (1/5/15 min): ' . ($runMetadata['host_load_average_start'] ?? 'unavailable');
            $lines[] = '- Host load average at end (1/5/15 min): ' . ($runMetadata['host_load_average_end'] ?? 'pending post-run capture');
            $methods = $runMetadata['fetch_methods_enabled'] ?? [];
            $lines[] = '- Fetch methods enabled: ' . ($methods === [] ? 'none reported' : '`' . implode('`, `', $methods) . '`');
            if (is_string($runMetadata['warning'] ?? null)) {
                $lines[] = '- Warning: ' . $runMetadata['warning'];
            }
            $lines[] = '';
        }

        array_push(
            $lines,
            sprintf(
                'Source coverage: **%s** (%d matrix rows).',
                $summary['source_coverage'],
                $summary['rows'],
            ),
            '',
            '## Verdict counts',
            '',
            '| Verdict | Count |',
            '| --- | ---: |',
        );

        foreach ($summary['by_verdict'] as $verdict => $count) {
            $lines[] = sprintf('| %s | %d |', $verdict, $count);
        }

        $lines[] = '';
        $lines[] = '## Error code counts';
        $lines[] = '';
        $lines[] = '| Error code | Count |';
        $lines[] = '| --- | ---: |';
        foreach ($summary['by_error_code'] as $errorCode => $count) {
            $lines[] = sprintf('| %s | %d |', $errorCode, $count);
        }

        $lines[] = '';
        $lines[] = '## Source results';
        $lines[] = '';
        $lines[] = '| Source | Capability | URL | Method | HTTP | Challenge | Fields | Verdict | Reason |';
        $lines[] = '| --- | --- | --- | --- | ---: | --- | ---: | --- | --- |';
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %d/%d | %s | %s |',
                self::cell($row->site),
                self::cell($row->capability),
                self::cell($row->url),
                self::cell($row->method ?? '—'),
                $row->status === null ? '—' : (string) $row->status,
                $row->challenge ? 'yes' : 'no',
                $row->fieldsParsed,
                $row->fieldsRequired,
                $row->verdict->value,
                self::cell($row->reason ?? '—'),
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param list<MatrixRow> $rows
     *  @param array<string, mixed> $summary
     */
    public static function json(array $rows, array $summary, ?array $runMetadata = null): string
    {
        $data = [
            'schema' => 'crawlerx.source-matrix.v1',
            'summary' => $summary,
            'rows' => array_map(static fn(MatrixRow $row): array => $row->toArray(), $rows),
        ];
        if ($runMetadata !== null) {
            $data['run_metadata'] = $runMetadata;
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /** @param list<array<string, mixed>> $rows @return list<MatrixRow> */
    public static function rowsFromArray(array $rows): array
    {
        return array_map(static fn(array $row): MatrixRow => new MatrixRow(
            site: (string) $row['site'],
            capability: (string) $row['capability'],
            url: (string) $row['url'],
            method: is_string($row['method'] ?? null) ? $row['method'] : null,
            status: is_int($row['status'] ?? null) ? $row['status'] : null,
            challenge: (bool) ($row['challenge'] ?? false),
            fieldsParsed: (int) ($row['fields_parsed'] ?? 0),
            fieldsRequired: (int) ($row['fields_required'] ?? 0),
            verdict: Verdict::from((string) $row['verdict']),
            reason: is_string($row['reason'] ?? null) ? $row['reason'] : null,
            errorCode: is_string($row['error_code'] ?? null) ? CrawlErrorCode::tryFrom($row['error_code']) : null,
        ), $rows);
    }

    private static function cell(string $value): string
    {
        return str_replace(["\r", "\n", '|'], ['', ' ', '\\|'], $value);
    }
}

final class SourceMatrixRunMetadata
{
    /** @param list<string> $fetchMethodsEnabled @return array<string, mixed> */
    public static function fromEnvironment(array $fetchMethodsEnabled): array
    {
        $metadata = [
            'started_at_utc' => self::environmentValue('CRAWLERX_MATRIX_STARTED_AT_UTC') ?? gmdate('Y-m-d\TH:i:s\Z'),
            'ended_at_utc' => null,
            'tool_git_sha' => self::environmentValue('CRAWLERX_MATRIX_TOOL_GIT_SHA') ?? 'unavailable',
            'host_cpu_count' => self::cpuCount(self::environmentValue('CRAWLERX_MATRIX_HOST_CPU_COUNT')),
            'host_load_average_start' => self::environmentValue('CRAWLERX_MATRIX_HOST_LOAD_START') ?? 'unavailable',
            'host_load_average_end' => null,
            'fetch_methods_enabled' => $fetchMethodsEnabled,
        ];
        $warning = self::loadWarning($metadata);
        if ($warning !== null) {
            $metadata['warning'] = $warning;
        }

        return $metadata;
    }

    public static function environmentValue(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $metadata */
    public static function loadWarning(array $metadata): ?string
    {
        $cpuCount = $metadata['host_cpu_count'] ?? null;
        $loadAverages = preg_split('/[\s,]+/', trim((string) ($metadata['host_load_average_start'] ?? ''))) ?: [];
        if (! is_int($cpuCount) || ! is_numeric($loadAverages[0] ?? null)) {
            return null;
        }

        $oneMinuteLoad = (float) $loadAverages[0];
        if ($oneMinuteLoad <= $cpuCount) {
            return null;
        }

        return sprintf('Host 1-minute load average %.2f exceeded the CPU count %d at matrix start.', $oneMinuteLoad, $cpuCount);
    }

    private static function cpuCount(?string $value): ?int
    {
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}

/** @codeCoverageIgnore HTTP/browser integration boundary; its guarded handlers are unit-tested. */
final class LiveMatrixProbe
{
    public function __construct(private readonly PolitenessGuard $guard)
    {
    }

    /** @return list<string> */
    public function enabledFetchMethods(): array
    {
        $runtime = FetchRuntimeConfig::fromEnvironment();
        $methods = [FetchMethod::Http->value];

        if ($runtime->curlImpersonateBinary !== null) {
            $methods[] = FetchMethod::CurlImpersonate->value;
        }
        if ($runtime->playwrightUrl !== null || is_file($runtime->playwrightScript)) {
            array_push(
                $methods,
                FetchMethod::Playwright->value,
                FetchMethod::PlaywrightStealth->value,
                FetchMethod::ChromeStealth->value,
            );
        }
        if (is_file($runtime->puppeteerScript)) {
            $methods[] = FetchMethod::PuppeteerStealth->value;
        }
        if ($runtime->flaresolverrUrl !== null) {
            $methods[] = FetchMethod::Flaresolverr->value;
        }

        return $methods;
    }

    public function __invoke(
        AdapterManifestDto $manifest,
        FixtureSampleDto $sample,
        CrawlType $type,
    ): MatrixObservation {
        $trace = new MatrixFetchTrace();
        CrawlerXFactory::reset();
        CrawlerXFactory::useFetchChain($this->fetchChain($manifest, $sample, $trace));

        try {
            $builder = CrawlerX::url($sample->url)->site($manifest->slug)->type($type);
            if ($manifest->slug === 'javlibrary') {
                $builder = $builder->options(new CrawlOptionsDto(
                    fetch: new FetchOptionsDto(method: FetchMethod::Flaresolverr, noFallback: true),
                ));
            } elseif (in_array($manifest->slug, ['javbtc', 'minnanoav'], true)) {
                $builder = $builder->options(new CrawlOptionsDto(
                    fetch: new FetchOptionsDto(profile: FetchProfile::BrowserLikely),
                ));
            }

            return new MatrixObservation($builder->tryCrawl(), $trace->lastResult());
        } finally {
            CrawlerXFactory::reset();
        }
    }

    private function fetchChain(
        AdapterManifestDto $manifest,
        FixtureSampleDto $sample,
        MatrixFetchTrace $trace,
    ): FetchFallbackChain {
        $runtime = FetchRuntimeConfig::fromEnvironment();
        $runner = new ProcOpenProcessRunner();
        $browserRunner = $runtime->playwrightUrl === null
            ? $runner
            : new BrowserServiceProcessRunner($runtime->playwrightUrl);
        $cookies = new CookieHandoffStore();
        $sessions = new SessionStore();
        $playwright = new PlaywrightFamilyFetchHandler($runtime, $browserRunner);
        $handlers = [
            FetchMethod::Http->value => new MatrixFetchMethodHandler(
                new HttpFetchHandler(new ClientFactory(), $cookies, $sessions),
                $this->guard,
                $trace,
                $manifest->slug,
                $sample->type,
            ),
            FetchMethod::CurlImpersonate->value => new MatrixFetchMethodHandler(
                new CurlImpersonateFetchHandler($runtime, $runner, $cookies, $sessions),
                $this->guard,
                $trace,
                $manifest->slug,
                $sample->type,
            ),
            FetchMethod::Playwright->value => new MatrixFetchMethodHandler($playwright, $this->guard, $trace, $manifest->slug, $sample->type),
            FetchMethod::PlaywrightStealth->value => new MatrixFetchMethodHandler($playwright, $this->guard, $trace, $manifest->slug, $sample->type),
            FetchMethod::ChromeStealth->value => new MatrixFetchMethodHandler($playwright, $this->guard, $trace, $manifest->slug, $sample->type),
            FetchMethod::PuppeteerStealth->value => new MatrixFetchMethodHandler(
                new PuppeteerStealthFetchHandler($runtime, $browserRunner),
                $this->guard,
                $trace,
                $manifest->slug,
                $sample->type,
            ),
            FetchMethod::Flaresolverr->value => new MatrixFetchMethodHandler(
                new FlaresolverrFetchHandler($runtime, null, $sessions),
                $this->guard,
                $trace,
                $manifest->slug,
                $sample->type,
            ),
        ];

        return new FetchFallbackChain(
            $handlers,
            $cookies,
            $sessions,
            runtime: $runtime,
            hostThrottle: new HostThrottle(nodeId: $runtime->nodeId),
            hostCircuit: new HostCircuit(nodeId: $runtime->nodeId),
            maxTransientRetries: 0,
        );
    }
}

final class SourceMatrixCommand
{
    /** @var Closure(string, string): bool */
    private readonly Closure $writer;

    /** @var Closure(string): void */
    private readonly Closure $output;

    /** @var Closure(string): void */
    private readonly Closure $errorOutput;

    /**
     * @param array<string, AdapterManifestDto> $manifests
     * @param callable(AdapterManifestDto, FixtureSampleDto, CrawlType): MatrixObservation $probe
     * @param callable(string, string): bool|null $writer
     * @param callable(string): void|null $output
     * @param callable(string): void|null $errorOutput
     * @param array<string, mixed>|null $runMetadata
     */
    public function __construct(
        private readonly array $manifests,
        callable $probe,
        private readonly string $projectRoot,
        ?callable $writer = null,
        ?callable $output = null,
        ?callable $errorOutput = null,
        private readonly ?PolitenessGuard $guard = null,
        private readonly ?array $runMetadata = null,
    ) {
        $this->probe = Closure::fromCallable($probe);
        $this->writer = $writer === null ? Closure::fromCallable(self::writeFile(...)) : Closure::fromCallable($writer);
        $this->output = Closure::fromCallable($output ?? static function (string $message): void {
            fwrite(STDOUT, $message . "\n");
        });
        $this->errorOutput = Closure::fromCallable($errorOutput ?? static function (string $message): void {
            fwrite(STDERR, $message . "\n");
        });
    }

    /** @var Closure(AdapterManifestDto, FixtureSampleDto, CrawlType): MatrixObservation */
    private readonly Closure $probe;

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        if ($arguments === ['--finalize-report']) {
            return $this->finalizeReport();
        }

        $siteFilter = null;
        foreach ($arguments as $argument) {
            if ($argument === '--help' || $argument === '-h') {
                ($this->output)('Usage: php tools/source-matrix.php [--site=SLUG] | --finalize-report');
                return 0;
            }

            if (! str_starts_with($argument, '--site=')) {
                ($this->errorOutput)("Unknown option: {$argument}");
                return 2;
            }

            $siteFilter = substr($argument, strlen('--site='));
            if ($siteFilter === '') {
                ($this->errorOutput)('--site requires a slug.');
                return 2;
            }
        }

        if ($siteFilter !== null && ! isset($this->manifests[$siteFilter])) {
            ($this->errorOutput)("Unknown site [{$siteFilter}].");
            return 2;
        }

        $selected = $siteFilter === null ? $this->manifests : [$siteFilter => $this->manifests[$siteFilter]];
        $runMetadata = $this->runMetadata ?? SourceMatrixRunMetadata::fromEnvironment([]);
        if (! is_string($runMetadata['warning'] ?? null)) {
            $warning = SourceMatrixRunMetadata::loadWarning($runMetadata);
            if ($warning !== null) {
                $runMetadata['warning'] = $warning;
            }
        }
        if (is_string($runMetadata['warning'] ?? null)) {
            ($this->errorOutput)('WARNING: ' . $runMetadata['warning']);
        }

        $rows = (new SourceMatrixRunner($this->probe, $this->guard))->run($selected, $siteFilter);
        $summary = SourceMatrixReport::summary($rows, count($selected));
        $jsonPath = $this->projectRoot . '/build/source-matrix.json';
        $reportPath = $this->projectRoot . '/artifacts/reports/source-matrix.md';

        if (! ($this->writer)($jsonPath, SourceMatrixReport::json($rows, $summary, $runMetadata))) {
            ($this->errorOutput)('Unable to write source matrix outputs.');
            return 1;
        }
        if (! ($this->writer)($reportPath, SourceMatrixReport::markdown($rows, $summary, $runMetadata))) {
            ($this->errorOutput)('Unable to write source matrix outputs.');
            return 1;
        }

        ($this->output)(sprintf(
            'Source matrix: %s sources, %d rows; %d working, %d degraded, %d blocked.',
            $summary['source_coverage'],
            $summary['rows'],
            $summary['by_verdict'][Verdict::Working->value],
            $summary['by_verdict'][Verdict::Degraded->value],
            $summary['by_verdict'][Verdict::Blocked->value],
        ));

        return 0;
    }

    private function finalizeReport(): int
    {
        $jsonPath = $this->projectRoot . '/build/source-matrix.json';
        $json = is_file($jsonPath) ? file_get_contents($jsonPath) : false;
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data) || ! is_array($data['rows'] ?? null) || ! is_array($data['summary'] ?? null)) {
            ($this->errorOutput)('Unable to read source matrix data for report finalization.');
            return 1;
        }

        $runMetadata = is_array($data['run_metadata'] ?? null) ? $data['run_metadata'] : [];
        $runMetadata['ended_at_utc'] = SourceMatrixRunMetadata::environmentValue('CRAWLERX_MATRIX_ENDED_AT_UTC') ?? gmdate('Y-m-d\TH:i:s\Z');
        $runMetadata['host_load_average_end'] = SourceMatrixRunMetadata::environmentValue('CRAWLERX_MATRIX_HOST_LOAD_END') ?? 'unavailable';
        $data['run_metadata'] = $runMetadata;

        if (! ($this->writer)($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n")) {
            ($this->errorOutput)('Unable to write finalized source matrix data.');
            return 1;
        }

        $reportPath = $this->projectRoot . '/artifacts/reports/source-matrix.md';
        $report = SourceMatrixReport::markdown(
            SourceMatrixReport::rowsFromArray($data['rows']),
            $data['summary'],
            $runMetadata,
        );
        if (! ($this->writer)($reportPath, $report)) {
            ($this->errorOutput)('Unable to write finalized source matrix report.');
            return 1;
        }

        return 0;
    }

    /** @codeCoverageIgnore Filesystem adapter for command output. */
    private static function writeFile(string $path, string $contents): bool
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $contents) !== false;
    }
}

/** @codeCoverageIgnore Live registry and external fetch setup. */
function runSourceMatrixCommand(array $arguments, string $projectRoot): int
{
    // Service addresses come from the project .env; real environment variables win.
    Env::applyToProcess($projectRoot . '/.env');
    $inProcessRun = $arguments !== ['--finalize-report'] && ! in_array('--help', $arguments, true) && ! in_array('-h', $arguments, true);
    if ($inProcessRun) {
        primeMatrixRunEnvironment($projectRoot);
    }

    $manifests = (new FileAdapterManifestRegistry())->all();
    $guard = new PolitenessGuard(new RequestPacer());
    $probe = new LiveMatrixProbe($guard);
    $command = new SourceMatrixCommand(
        $manifests,
        $probe,
        $projectRoot,
        guard: $guard,
        runMetadata: SourceMatrixRunMetadata::fromEnvironment($probe->enabledFetchMethods()),
    );

    $exitCode = $command->run($arguments);
    if ($inProcessRun && $exitCode === 0) {
        // Finish the report in the same process so no wrapper has to carry the end-of-run values.
        putenv('CRAWLERX_MATRIX_ENDED_AT_UTC=' . gmdate('Y-m-d\TH:i:s\Z'));
        putenv('CRAWLERX_MATRIX_HOST_LOAD_END=' . matrixLoadAverage());
        $exitCode = $command->run(['--finalize-report']);
    }

    return $exitCode;
}

/** @codeCoverageIgnore Reads the local machine; the metadata consumers are unit-tested. */
function matrixLoadAverage(): string
{
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

    return is_array($load) ? sprintf('%.2f %.2f %.2f', $load[0], $load[1], $load[2]) : 'unavailable';
}

/**
 * Fills the run metadata the report needs when the caller did not provide it (a plain `php tools/source-matrix.php`
 * in any runtime). Values already set in the environment always win.
 *
 * @codeCoverageIgnore Reads the local machine; the metadata consumers are unit-tested.
 */
function primeMatrixRunEnvironment(string $projectRoot): void
{
    $defaults = [
        'CRAWLERX_MATRIX_STARTED_AT_UTC' => gmdate('Y-m-d\TH:i:s\Z'),
        'CRAWLERX_MATRIX_HOST_LOAD_START' => matrixLoadAverage(),
    ];
    $sha = @shell_exec('git -C ' . escapeshellarg($projectRoot) . ' rev-parse HEAD 2>/dev/null');
    if (is_string($sha) && preg_match('/^[0-9a-f]{40}$/', trim($sha)) === 1) {
        $defaults['CRAWLERX_MATRIX_TOOL_GIT_SHA'] = trim($sha);
    }
    $cpus = @shell_exec('nproc 2>/dev/null');
    if (is_string($cpus) && ctype_digit(trim($cpus))) {
        $defaults['CRAWLERX_MATRIX_HOST_CPU_COUNT'] = trim($cpus);
    }

    foreach ($defaults as $name => $value) {
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(runSourceMatrixCommand(array_slice($argv, 1), dirname(__DIR__)));
}
