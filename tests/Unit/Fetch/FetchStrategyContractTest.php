<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\TerminalStatus;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use PHPUnit\Framework\TestCase;

final class FetchStrategyContractTest extends TestCase
{
    public function test_tc_fs_03_and_04_terminal_404_and_410_stop_without_fallback(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(status: 404, body: '<html>not found</html>'),
            FetchMethod::Playwright->value => $this->handler(ok: true, body: '<html>browser</html>'),
        ]);

        try {
            $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http, FetchMethod::Playwright]);
            self::fail('Expected terminal 404 exception.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::NotFound, $exception->errorCode);
            self::assertFalse($exception->retryable);
            self::assertCount(1, $exception->fetch?->attempts ?? []);
        }

        $terminal = TerminalStatus::fromResult($this->fetchResult(status: 410, body: '<html>gone</html>'));
        self::assertNotNull($terminal);
        self::assertSame(CrawlErrorCode::Gone, $terminal->code);
        self::assertFalse($terminal->retryable);
    }

    public function test_tc_fs_05_retry_after_is_rate_limited(): void
    {
        $terminal = TerminalStatus::fromResult($this->fetchResult(
            status: 503,
            body: '<html>busy</html>',
            headers: ['Retry-After' => ['30']],
        ));

        self::assertNotNull($terminal);
        self::assertSame(CrawlErrorCode::RateLimited, $terminal->code);
        self::assertTrue($terminal->retryable);
        self::assertSame(30, $terminal->retryAfterSeconds);
    }

    public function test_tc_fs_06_soft404_marker_is_terminal(): void
    {
        $terminal = TerminalStatus::fromResult(
            $this->fetchResult(status: 200, body: '<html><h1>Record unavailable</h1></html>'),
            ['record unavailable'],
        );

        self::assertNotNull($terminal);
        self::assertSame(CrawlErrorCode::NotFound, $terminal->code);
    }

    public function test_tc_fs_06_soft404_marker_ignores_script_content(): void
    {
        $terminal = TerminalStatus::fromResult(
            new FetchResultDto(
                ok: true,
                body: '<html><script>const text = "404 not found";</script></html>',
                status: 200,
                methodUsed: FetchMethod::Http,
                elapsedMs: 1,
                challengeDetected: false,
            ),
            ['404 not found'],
        );

        self::assertNull($terminal);
    }

    public function test_tc_fs_02_ready_marker_missing_forces_the_next_handler(): void
    {
        $calls = new \stdClass();
        $calls->browser = 0;
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(ok: true, status: 200, body: '<html><div id="shell"></div></html>'),
            FetchMethod::Playwright->value => $this->handler(
                ok: true,
                status: 200,
                body: '<html><p id="movie">ready</p></html>',
                calls: $calls,
                callProperty: 'browser',
            ),
        ]);

        $result = $chain->fetch(
            'https://example.test',
            $this->profile(readyMarkers: ['#movie']),
            [FetchMethod::Http, FetchMethod::Playwright],
        );

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::Playwright, $result->methodUsed);
        self::assertSame(1, $calls->browser);
        self::assertCount(2, $result->attempts);
    }

    public function test_tc_fs_09_flaresolverr_is_not_called_without_a_challenge(): void
    {
        $calls = new \stdClass();
        $calls->flare = 0;
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(ok: false, status: 500, body: '<html>temporary</html>'),
            FetchMethod::Flaresolverr->value => $this->handler(
                ok: true,
                body: '<html>flare</html>',
                calls: $calls,
                callProperty: 'flare',
            ),
        ]);

        try {
            $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr]);
            self::fail('Expected the chain to fail without calling FlareSolverr.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(0, $calls->flare);
            self::assertSame(CrawlErrorCode::Network, $exception->errorCode);
        }
    }

    public function test_tc_fs_10_flaresolverr_runs_after_a_challenge(): void
    {
        $calls = new \stdClass();
        $calls->flare = 0;
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(ok: false, status: 403, body: '<title>Just a moment...</title>'),
            FetchMethod::Flaresolverr->value => $this->handler(
                ok: true,
                body: '<html>flare result</html>',
                calls: $calls,
                callProperty: 'flare',
            ),
        ]);

        $result = $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr]);

        self::assertTrue($result->ok);
        self::assertSame(1, $calls->flare);
    }

    public function test_tc_fs_11_default_profile_is_adaptive_and_legacy_true_is_browser_likely(): void
    {
        $registry = new FileAdapterManifestRegistry();
        self::assertCount(25, $registry->all());
        foreach ($registry->all() as $manifest) {
            self::assertInstanceOf(FetchProfile::class, $manifest->fetchProfile);
            self::assertNotSame([], $manifest->readyMarkers);
            self::assertNotSame([], $manifest->soft404Markers);
        }

        $legacy = new AdapterManifestDto(
            slug: 'legacy',
            displayName: 'Legacy',
            baseUrl: 'https://example.test',
            adapterClass: \JOOservices\CrawlerX\Adapters\Onejav\OnejavCrawler::class,
            pagination: 'query',
            capabilities: ['listing'],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: [],
            playwrightFetchEnabled: true,
        );

        self::assertSame(FetchProfile::BrowserLikely, SiteProfileDto::fromManifest($legacy)->fetchProfile);
    }

    public function test_tc_fs_13_playwright_stealth_alias_remains_accepted(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::PlaywrightStealth->value => $this->handler(ok: true, body: '<html>alias</html>'),
        ]);

        $result = $chain->fetch('https://example.test', $this->profile(), [FetchMethod::PlaywrightStealth]);

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::PlaywrightStealth, $result->methodUsed);
    }

    public function test_tc_er_01_new_error_codes_have_retry_defaults(): void
    {
        self::assertFalse(CrawlErrorCode::NotFound->defaultRetryable());
        self::assertFalse(CrawlErrorCode::Gone->defaultRetryable());
        self::assertTrue(CrawlErrorCode::RateLimited->defaultRetryable());
        self::assertTrue(CrawlErrorCode::Timeout->defaultRetryable());
        self::assertTrue(CrawlErrorCode::Challenge->defaultRetryable());
        self::assertTrue(CrawlErrorCode::Network->defaultRetryable());
        self::assertFalse(CrawlErrorCode::AuthRequired->defaultRetryable());
    }

    public function test_tc_er_02_network_failure_is_retryable(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(ok: false, status: 0, body: '', error: 'connection refused'),
        ]);

        try {
            $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http]);
            self::fail('Expected network failure.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Network, $exception->errorCode);
            self::assertTrue($exception->retryable);
        }
    }

    public function test_tc_er_03_existing_error_values_are_unchanged(): void
    {
        self::assertSame('blocked', CrawlErrorCode::Blocked->value);
        self::assertSame('parse_failed', CrawlErrorCode::ParseFailed->value);
        self::assertSame('unsupported_url', CrawlErrorCode::UnsupportedUrl->value);
    }

    public function test_tc_er_04_error_serialization_contains_retry_hints(): void
    {
        $error = new CrawlErrorDto(
            code: CrawlErrorCode::RateLimited,
            message: 'retry later',
            retryable: true,
            retryAfterSeconds: 30,
        );
        $serialized = json_decode(json_encode($error, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        self::assertTrue($serialized['retryable']);
        self::assertSame(30, $serialized['retryAfterSeconds']);
    }

    public function test_tc_fs_07_and_08_budget_caps_and_deadline(): void
    {
        $budget = FetchBudget::start(new FetchOptionsDto(deadlineSeconds: 30));

        self::assertSame(20, $budget->methodCap(FetchMethod::Http));
        self::assertSame(45, $budget->methodCap(FetchMethod::Playwright));
        self::assertSame(60, $budget->methodCap(FetchMethod::Flaresolverr));
        self::assertSame(30, $budget->deadlineSeconds());
        self::assertTrue($budget->canStart(FetchMethod::Http));
    }

    private function fetchResult(int $status, string $body, array $headers = []): FetchResultDto
    {
        return new FetchResultDto(
            ok: false,
            body: $body,
            status: $status,
            methodUsed: FetchMethod::Http,
            elapsedMs: 1,
            challengeDetected: false,
            headers: $headers,
            error: 'failed',
        );
    }

    private function profile(array $readyMarkers = []): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: FetchMethod::defaultChain(),
            http: new HttpProfileDto(),
            readyMarkers: $readyMarkers === [] ? [] : ['listing' => $readyMarkers],
            soft404Markers: ['page not found'],
        );
    }

    private function handler(
        bool $ok = false,
        int $status = 403,
        string $body = '',
        ?string $error = null,
        ?\stdClass $calls = null,
        ?string $callProperty = null,
    ): FetchMethodHandler {
        return new class ($ok, $status, $body, $error, $calls, $callProperty) implements FetchMethodHandler {
            public function __construct(
                private readonly bool $ok,
                private readonly int $status,
                private readonly string $body,
                private readonly ?string $error,
                private readonly ?\stdClass $calls,
                private readonly ?string $callProperty,
            ) {
            }

            public function supports(FetchMethod $method): bool
            {
                return true;
            }

            public function fetch(
                string $url,
                SiteProfileDto $profile,
                FetchMethod $method,
                ?CrawlOptionsDto $options = null,
            ): FetchResultDto {
                if ($this->calls !== null && $this->callProperty !== null) {
                    ++$this->calls->{$this->callProperty};
                }

                return new FetchResultDto(
                    ok: $this->ok,
                    body: $this->body,
                    status: $this->status,
                    methodUsed: $method,
                    elapsedMs: 1,
                    challengeDetected: ChallengeDetector::isChallenge($this->body, $this->status),
                    finalUrl: $url,
                    error: $this->error ?? ($this->ok ? null : 'failed'),
                );
            }
        };
    }
}
