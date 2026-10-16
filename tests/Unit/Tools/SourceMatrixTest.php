<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Tools\SourceMatrix\MatrixClassifier;
use JOOservices\CrawlerX\Tools\SourceMatrix\MatrixFetchMethodHandler;
use JOOservices\CrawlerX\Tools\SourceMatrix\MatrixFetchTrace;
use JOOservices\CrawlerX\Tools\SourceMatrix\MatrixObservation;
use JOOservices\CrawlerX\Tools\SourceMatrix\PolitenessGuard;
use JOOservices\CrawlerX\Tools\SourceMatrix\RequestPacer;
use JOOservices\CrawlerX\Tools\SourceMatrix\SourceMatrixReport;
use JOOservices\CrawlerX\Tools\SourceMatrix\SourceMatrixCommand;
use JOOservices\CrawlerX\Tools\SourceMatrix\SourceMatrixRunner;
use JOOservices\CrawlerX\Tools\SourceMatrix\Verdict;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/source-matrix.php';

final class SourceMatrixTest extends TestCase
{
    public function test_success_with_every_required_field_is_working(): void
    {
        $fetch = $this->fetchResult(status: 200);

        self::assertSame(
            [Verdict::Working, null],
            MatrixClassifier::classify($fetch, null, 2, 2),
        );
    }

    public function test_missing_required_fields_are_degraded_with_field_names(): void
    {
        self::assertSame(
            [Verdict::Degraded, 'movie.external_id'],
            MatrixClassifier::classify($this->fetchResult(status: 200), null, 1, 2, ['movie.external_id']),
        );
    }

    public function test_challenges_timeouts_and_forbidden_statuses_are_blocked(): void
    {
        self::assertSame(
            [Verdict::Blocked, 'challenge'],
            MatrixClassifier::classify($this->fetchResult(status: 200, challenge: true), null, 2, 2),
        );
        self::assertSame(
            [Verdict::Blocked, 'timeout'],
            MatrixClassifier::classify($this->fetchResult(status: 0, error: 'request timed out'), null, 0, 2),
        );
        self::assertSame(
            [Verdict::Blocked, 'http_403'],
            MatrixClassifier::classify($this->fetchResult(status: 403), CrawlErrorCode::Blocked, 0, 2),
        );
        self::assertSame(
            [Verdict::Blocked, 'timeout'],
            MatrixClassifier::classify(null, CrawlErrorCode::Timeout, 0, 2),
        );
    }

    public function test_other_typed_errors_and_failed_fetches_are_degraded(): void
    {
        self::assertSame(
            [Verdict::Degraded, 'not_found'],
            MatrixClassifier::classify(null, CrawlErrorCode::NotFound, 0, 2),
        );
        self::assertSame(
            [Verdict::Degraded, 'fetch_failed'],
            MatrixClassifier::classify($this->fetchResult(status: 0, ok: false), null, 2, 2),
        );
        self::assertSame(
            [Verdict::Degraded, 'required_fields_missing'],
            MatrixClassifier::classify(null, null, 0, 2),
        );
    }

    public function test_request_budget_caps_capability_at_three_and_spaces_host_requests(): void
    {
        $now = 0.0;
        $starts = [];
        $pacer = new RequestPacer(
            clock: static fn(): float => $now,
            sleeper: static function (int $microseconds) use (&$now): void {
                $now += $microseconds / 1_000_000;
            },
        );
        $guard = new PolitenessGuard($pacer);
        $settings = ['default_gap_seconds' => 2.0, 'min_gap_seconds' => 1.0, 'max_gap_seconds' => 10.0];

        for ($request = 0; $request < 3; ++$request) {
            self::assertTrue($guard->beforeRequest('alpha', 'detail', 'https://example.test/item', $settings));
            $starts[] = $now;
        }

        self::assertFalse($guard->beforeRequest('alpha', 'detail', 'https://example.test/item', $settings));
        self::assertSame(3, $guard->requestCount('alpha', 'detail'));
        self::assertSame([0.0, 5.0, 10.0], $starts);
    }

    public function test_manifest_throttle_above_five_seconds_is_preserved_per_host(): void
    {
        $now = 0.0;
        $pacer = new RequestPacer(
            clock: static fn(): float => $now,
            sleeper: static function (int $microseconds) use (&$now): void {
                $now += $microseconds / 1_000_000;
            },
        );
        $guard = new PolitenessGuard($pacer);

        self::assertTrue($guard->beforeRequest(
            'alpha',
            'detail',
            'https://shared.example/item/1',
            ['default_gap_seconds' => 8.0, 'min_gap_seconds' => 5.0, 'max_gap_seconds' => 60.0],
        ));
        self::assertTrue($guard->beforeRequest(
            'beta',
            'listing',
            'https://shared.example/list',
            ['default_gap_seconds' => 2.0, 'min_gap_seconds' => 0.5, 'max_gap_seconds' => 15.0],
        ));

        self::assertSame(8.0, $now);
    }

    public function test_pacer_skips_empty_hosts_and_default_sleeper_handles_a_short_gap(): void
    {
        $now = 0.0;
        $pacer = new RequestPacer(
            clock: static fn(): float => $now,
            minimumGapSeconds: 0.000001,
        );

        $pacer->wait('', null);
        $pacer->wait('example.test', null);
        $pacer->wait('example.test', null);

        self::assertGreaterThanOrEqual(0.0, $now);
    }

    public function test_guard_rejects_urls_without_a_host(): void
    {
        $guard = new PolitenessGuard(new RequestPacer());

        self::assertFalse($guard->beforeRequest('alpha', 'detail', 'not-a-url', null));
        self::assertSame(0, $guard->requestCount('alpha', 'detail'));
    }

    public function test_fetch_handler_records_typed_results_and_denies_over_budget_requests(): void
    {
        $now = 0.0;
        $inner = new RecordingFetchMethodHandler($this->fetchResult(status: 200));
        $guard = new PolitenessGuard(new RequestPacer(
            clock: static fn(): float => $now,
            sleeper: static function (int $microseconds) use (&$now): void {
                $now += $microseconds / 1_000_000;
            },
        ));
        $trace = new MatrixFetchTrace();
        $handler = new MatrixFetchMethodHandler($inner, $guard, $trace, 'alpha', 'detail');
        $profile = $this->siteProfile();

        self::assertTrue($handler->supports(FetchMethod::Http));
        for ($request = 0; $request < 3; ++$request) {
            $handler->fetch('https://example.test/item', $profile, FetchMethod::Http);
        }
        $denied = $handler->fetch('https://example.test/item', $profile, FetchMethod::Http);

        self::assertSame(3, $inner->calls);
        self::assertSame(200, $trace->lastResult()?->status);
        self::assertSame(0, $denied->status);
        self::assertSame('request_budget_exhausted', $denied->error);
    }

    public function test_runner_covers_each_source_capability_and_classifies_results(): void
    {
        $manifests = [];
        for ($source = 0; $source < 25; ++$source) {
            $slug = sprintf('source-%02d', $source);
            $manifests[$slug] = $this->manifest(
                $slug,
                'detail',
                new FixtureSampleDto('https://example.test/detail/1', 'sample', 'detail'),
            );
        }
        $probe = static function (
            AdapterManifestDto $manifest,
            FixtureSampleDto $sample,
            CrawlType $type,
        ): MatrixObservation {
            $item = new CrawlItemResultDto(
                url: 'https://example.test/detail/1',
                entityType: 'movie',
                meta: ['movie' => ['title' => 'Synthetic title', 'external_id' => '1']],
            );

            return new MatrixObservation(CrawlOutcomeDto::success($item), new FetchResultDto(
                ok: true,
                body: '<html>never persisted</html>',
                status: 200,
                methodUsed: FetchMethod::Http,
                elapsedMs: 10,
                challengeDetected: false,
                finalUrl: 'https://example.test/detail/1',
            ));
        };

        $rows = (new SourceMatrixRunner($probe))->run($manifests);
        $summary = SourceMatrixReport::summary($rows, 25);
        $report = SourceMatrixReport::markdown($rows, $summary);
        $json = SourceMatrixReport::json($rows, $summary);

        self::assertCount(25, $rows);
        self::assertSame('25/25', $summary['source_coverage']);
        self::assertTrue($summary['all_sources_covered']);
        self::assertSame(25, $summary['by_verdict'][Verdict::Working->value]);
        self::assertStringContainsString('source-00', $report);
        self::assertStringContainsString('source-24', $report);
        self::assertStringContainsString('Source coverage: **25/25**', $report);
        self::assertStringNotContainsString('never persisted', $json);
        self::assertStringContainsString('"error_code": null', $json);
    }

    public function test_runner_reports_required_login_as_blocked_without_fetching(): void
    {
        $called = false;
        $runner = new SourceMatrixRunner(static function (
            AdapterManifestDto $manifest,
            FixtureSampleDto $sample,
            CrawlType $type,
        ) use (&$called): MatrixObservation {
            $called = true;
            self::fail('Login-required source must not be fetched.');
        });

        $rows = $runner->run(['avfan' => $this->manifest(
            'avfan',
            'detail',
            new FixtureSampleDto('https://example.test/detail/1', 'sample', 'detail'),
        )]);

        self::assertFalse($called);
        self::assertSame(Verdict::Blocked, $rows[0]->verdict);
        self::assertSame(CrawlErrorCode::AuthRequired, $rows[0]->errorCode);
        self::assertSame('auth_required', $rows[0]->reason);
    }

    public function test_runner_reports_missing_manifest_sample_and_filters_sites(): void
    {
        $called = false;
        $runner = new SourceMatrixRunner(static function (
            AdapterManifestDto $manifest,
            FixtureSampleDto $sample,
            CrawlType $type,
        ) use (&$called): MatrixObservation {
            $called = true;
            self::fail('A missing sample must not be fetched.');
        });
        $missing = $this->manifest('alpha', 'listing', sample: null);
        $other = $this->manifest(
            'beta',
            'detail',
            new FixtureSampleDto('https://example.test/detail/1', 'sample', 'detail'),
        );

        $rows = $runner->run(['alpha' => $missing, 'beta' => $other], 'alpha');

        self::assertFalse($called);
        self::assertCount(1, $rows);
        self::assertSame('missing_sample_url', $rows[0]->reason);
        self::assertSame(CrawlErrorCode::Unknown, $rows[0]->errorCode);
    }

    public function test_runner_handles_unsupported_capabilities_and_typed_fetch_failures(): void
    {
        $probe = static function (
            AdapterManifestDto $manifest,
            FixtureSampleDto $sample,
            CrawlType $type,
        ): MatrixObservation {
            return new MatrixObservation(
                CrawlOutcomeDto::failure(new CrawlErrorDto(CrawlErrorCode::NotFound, 'No content')),
                null,
            );
        };
        $runner = new SourceMatrixRunner($probe);
        $manifests = [
            'unknown' => $this->manifest(
                'unknown',
                'unknown_capability',
                new FixtureSampleDto('https://example.test/unknown', 'sample', 'unknown_capability'),
            ),
            'gallery' => $this->manifest(
                'gallery',
                'gallery',
                new FixtureSampleDto('https://example.test/gallery', 'sample', 'gallery'),
            ),
            'performer' => $this->manifest(
                'performer',
                'performer_detail',
                new FixtureSampleDto('https://example.test/performer', 'sample', 'performer_detail'),
            ),
        ];

        $rows = $runner->run($manifests);

        self::assertSame(2, $rows[0]->fieldsRequired);
        self::assertSame('not_found', $rows[0]->reason);
        self::assertSame(3, $rows[1]->fieldsRequired);
        self::assertSame('unsupported_capability', $rows[2]->reason);
        self::assertSame(0, $rows[2]->fieldsRequired);
    }

    public function test_command_writes_json_and_markdown_for_the_selected_site(): void
    {
        $files = [];
        $messages = [];
        $probe = fn(AdapterManifestDto $source, FixtureSampleDto $sample, CrawlType $type): MatrixObservation => new MatrixObservation(
            CrawlOutcomeDto::success(new CrawlItemResultDto(
                url: 'https://example.test/detail/1',
                entityType: 'movie',
                meta: ['movie' => ['title' => 'Synthetic', 'external_id' => '1']],
            )),
            $this->fetchResult(status: 200),
        );
        $manifest = $this->manifest(
            'alpha',
            'detail',
            new FixtureSampleDto('https://example.test/detail/1', 'sample', 'detail'),
        );
        $command = new SourceMatrixCommand(
            ['alpha' => $manifest, 'beta' => $this->manifest('beta', 'detail')],
            $probe,
            '/workspace',
            writer: static function (string $path, string $contents) use (&$files): bool {
                $files[$path] = $contents;

                return true;
            },
            output: static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
            errorOutput: static function (): void {
            },
        );

        self::assertSame(0, $command->run(['--site=alpha']));
        self::assertArrayHasKey('/workspace/build/source-matrix.json', $files);
        self::assertArrayHasKey('/workspace/artifacts/reports/source-matrix.md', $files);
        self::assertStringContainsString('"source_coverage": "1/1"', $files['/workspace/build/source-matrix.json']);
        self::assertStringContainsString('Source coverage: **1/1**', $files['/workspace/artifacts/reports/source-matrix.md']);
        self::assertSame(['Source matrix: 1/1 sources, 1 rows; 1 working, 0 degraded, 0 blocked.'], $messages);
    }

    public function test_command_handles_help_invalid_options_unknown_sites_and_write_errors(): void
    {
        $errors = [];
        $output = [];
        $command = new SourceMatrixCommand(
            [],
            static fn(): MatrixObservation => throw new \LogicException('Probe must not run.'),
            '/workspace',
            writer: static fn(): bool => false,
            output: static function (string $message) use (&$output): void {
                $output[] = $message;
            },
            errorOutput: static function (string $message) use (&$errors): void {
                $errors[] = $message;
            },
        );

        self::assertSame(0, $command->run(['--help']));
        self::assertSame(2, $command->run(['--unknown']));
        self::assertSame(2, $command->run(['--site=']));
        self::assertSame(2, $command->run(['--site=missing']));
        self::assertSame(['Usage: php tools/source-matrix.php [--site=SLUG]'], $output);
        self::assertSame([
            'Unknown option: --unknown',
            '--site requires a slug.',
            'Unknown site [missing].',
        ], $errors);

        $writeFailure = new SourceMatrixCommand(
            ['alpha' => $this->manifest(
                'alpha',
                'detail',
                new FixtureSampleDto('https://example.test/detail/1', 'sample', 'detail'),
            )],
            static fn(): MatrixObservation => new MatrixObservation(
                CrawlOutcomeDto::failure(new CrawlErrorDto(CrawlErrorCode::NotFound, 'No content')),
                null,
            ),
            '/workspace',
            writer: static fn(string $path, string $contents): bool => false,
            errorOutput: static function (string $message) use (&$errors): void {
                $errors[] = $message;
            },
        );

        self::assertSame(1, $writeFailure->run(['--site=alpha']));
        self::assertSame('Unable to write source matrix outputs.', $errors[3]);
    }

    private function manifest(string $slug, string $capability, ?FixtureSampleDto $sample = null): AdapterManifestDto
    {
        return new AdapterManifestDto(
            slug: $slug,
            displayName: $slug,
            baseUrl: 'https://example.test',
            adapterClass: \stdClass::class,
            pagination: 'none',
            capabilities: [$capability],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: [],
            fixtureSamples: $sample === null ? [] : [$sample],
        );
    }

    private function fetchResult(
        int $status,
        bool $challenge = false,
        ?string $error = null,
        bool $ok = true,
    ): FetchResultDto {
        return new FetchResultDto(
            ok: $ok,
            body: '',
            status: $status,
            methodUsed: FetchMethod::Http,
            elapsedMs: 1,
            challengeDetected: $challenge,
            error: $error,
        );
    }

    private function siteProfile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'alpha',
            displayName: 'Alpha',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http],
            http: new \JOOservices\CrawlerX\Dto\HttpProfileDto(30, true),
        );
    }
}

final class RecordingFetchMethodHandler implements FetchMethodHandler
{
    public int $calls = 0;

    public function __construct(private readonly FetchResultDto $result)
    {
    }

    public function supports(FetchMethod $method): bool
    {
        return true;
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?\JOOservices\CrawlerX\Dto\CrawlOptionsDto $options = null,
    ): FetchResultDto {
        ++$this->calls;

        return $this->result;
    }
}
