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
use JOOservices\CrawlerX\Tools\Canary\LoginCookiePolicy;
use JOOservices\CrawlerX\Tools\Canary\RequiredFields;

require_once dirname(__DIR__) . '/vendor/autoload.php';
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

    public function __construct(
        private readonly RequestPacer $pacer,
        private readonly int $maxRequestsPerCapability = 3,
    ) {
    }

    /** @param array<string, float>|null $settings */
    public function beforeRequest(string $site, string $capability, string $url, ?array $settings): bool
    {
        $key = $site . "\0" . $capability;
        if (($this->requests[$key] ?? 0) >= $this->maxRequestsPerCapability) {
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

        if ($fetch?->status === 403) {
            return [Verdict::Blocked, 'http_403'];
        }

        if ($errorCode === CrawlErrorCode::Timeout || self::isTimeout($fetch?->error)) {
            return [Verdict::Blocked, 'timeout'];
        }

        if (in_array($errorCode, [CrawlErrorCode::Blocked, CrawlErrorCode::AuthRequired], true)) {
            return [Verdict::Blocked, $errorCode->value];
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

    private static function isTimeout(?string $error): bool
    {
        if ($error === null) {
            return false;
        }

        $error = strtolower($error);

        return str_contains($error, 'timeout') || str_contains($error, 'timed out');
    }
}

final class SourceMatrixRunner
{
    /** @var Closure(AdapterManifestDto, FixtureSampleDto, CrawlType): MatrixObservation */
    private readonly Closure $probe;

    /** @param callable(AdapterManifestDto, FixtureSampleDto, CrawlType): MatrixObservation $probe */
    public function __construct(callable $probe)
    {
        $this->probe = Closure::fromCallable($probe);
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

                $observation = ($this->probe)($manifest, $sample, $type);
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
    public static function markdown(array $rows, array $summary): string
    {
        $lines = [
            '# Source matrix',
            '',
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
        ];

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
    public static function json(array $rows, array $summary): string
    {
        return json_encode([
            'schema' => 'crawlerx.source-matrix.v1',
            'summary' => $summary,
            'rows' => array_map(static fn(MatrixRow $row): array => $row->toArray(), $rows),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    private static function cell(string $value): string
    {
        return str_replace(["\r", "\n", '|'], ['', ' ', '\\|'], $value);
    }
}

/** @codeCoverageIgnore HTTP/browser integration boundary; its guarded handlers are unit-tested. */
final class LiveMatrixProbe
{
    public function __construct(private readonly PolitenessGuard $guard)
    {
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
     */
    public function __construct(
        private readonly array $manifests,
        callable $probe,
        private readonly string $projectRoot,
        ?callable $writer = null,
        ?callable $output = null,
        ?callable $errorOutput = null,
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
        $siteFilter = null;
        foreach ($arguments as $argument) {
            if ($argument === '--help' || $argument === '-h') {
                ($this->output)('Usage: php tools/source-matrix.php [--site=SLUG]');
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
        $rows = (new SourceMatrixRunner($this->probe))->run($selected, $siteFilter);
        $summary = SourceMatrixReport::summary($rows, count($selected));
        $jsonPath = $this->projectRoot . '/build/source-matrix.json';
        $reportPath = $this->projectRoot . '/artifacts/reports/source-matrix.md';

        if (! ($this->writer)($jsonPath, SourceMatrixReport::json($rows, $summary))) {
            ($this->errorOutput)('Unable to write source matrix outputs.');
            return 1;
        }
        if (! ($this->writer)($reportPath, SourceMatrixReport::markdown($rows, $summary))) {
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
    $manifests = (new FileAdapterManifestRegistry())->all();
    $guard = new PolitenessGuard(new RequestPacer());
    $command = new SourceMatrixCommand($manifests, new LiveMatrixProbe($guard), $projectRoot);

    return $command->run($arguments);
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(runSourceMatrixCommand(array_slice($argv, 1), dirname(__DIR__)));
}
