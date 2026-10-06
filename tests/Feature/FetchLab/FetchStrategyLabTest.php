<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\FetchLab;

use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class FetchStrategyLabTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CRAWLERX_FETCH_LAB') !== '1') {
            self::markTestSkipped('Set CRAWLERX_FETCH_LAB=1 to run the Compose fetch lab.');
        }
    }

    public function test_tc_fs_01_adaptive_static_uses_one_http_attempt(): void
    {
        $result = $this->chain()->fetch(
            $this->fixtureUrl('/static/movie/TC-FS-01'),
            $this->profile(),
            [FetchMethod::Http, FetchMethod::Playwright],
            type: CrawlType::Detail,
        );

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::Http, $result->methodUsed);
        self::assertCount(1, $result->attempts);
    }

    public function test_tc_fs_02_shell_http_is_unusable_and_browser_renders_ready_marker(): void
    {
        $result = $this->chain()->fetch(
            $this->fixtureUrl('/shell/movie/TC-FS-02'),
            $this->profile(),
            [FetchMethod::Http, FetchMethod::Playwright],
            type: CrawlType::Detail,
        );

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::Playwright, $result->methodUsed);
        self::assertStringContainsString('shell movie TC-FS-02', $result->body);
        self::assertCount(2, $result->attempts);
    }

    public function test_tc_fs_03_404_is_terminal_and_does_not_start_browser(): void
    {
        try {
            $this->chain()->fetch(
                $this->fixtureUrl('/status/404'),
                $this->profile(),
                [FetchMethod::Http, FetchMethod::Playwright],
                type: CrawlType::Detail,
            );
            self::fail('Expected not_found.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::NotFound, $exception->errorCode);
            self::assertCount(1, $exception->fetch?->attempts ?? []);
        }
    }

    public function test_tc_fs_04_410_is_terminal(): void
    {
        try {
            $this->chain()->fetch($this->fixtureUrl('/status/410'), $this->profile(), [FetchMethod::Http, FetchMethod::Playwright]);
            self::fail('Expected gone.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Gone, $exception->errorCode);
        }
    }

    public function test_tc_fs_05_429_retry_after_is_rate_limited(): void
    {
        try {
            $this->chain()->fetch($this->fixtureUrl('/status/429?retry=30'), $this->profile(), [FetchMethod::Http, FetchMethod::Playwright]);
            self::fail('Expected rate_limited.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::RateLimited, $exception->errorCode);
            self::assertSame(30, $exception->retryAfterSeconds);
        }
    }

    public function test_tc_fs_07_hang_exhausts_the_consumer_budget_without_flare(): void
    {
        try {
            $this->chain()->fetch(
                $this->fixtureUrl('/hang'),
                $this->profile(),
                [FetchMethod::Http, FetchMethod::Playwright, FetchMethod::Flaresolverr],
                $this->hangOptions(),
                CrawlType::Detail,
            );
            self::fail('Expected timeout.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Timeout, $exception->errorCode);
            self::assertNotContains(FetchMethod::Flaresolverr->value, array_column($exception->fetch?->attempts ?? [], 'method'));
        }
    }

    public function test_tc_fs_08_short_consumer_deadline_stops_hang(): void
    {
        try {
            $this->chain()->fetch(
                $this->fixtureUrl('/hang'),
                $this->profile(),
                [FetchMethod::Http],
                $this->hangOptions(),
            );
            self::fail('Expected timeout.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Timeout, $exception->errorCode);
        }
    }

    public function test_tc_fs_06_soft404_is_terminal(): void
    {
        try {
            $this->chain()->fetch($this->fixtureUrl('/soft404/TC-FS-06'), $this->profile(), [FetchMethod::Http, FetchMethod::Playwright]);
            self::fail('Expected not_found.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::NotFound, $exception->errorCode);
        }
    }

    public function test_tc_fs_10_challenge_calls_fixture_flaresolverr_once(): void
    {
        $result = $this->chain()->fetch(
            $this->fixtureUrl('/cf-bound/TC-FS-10'),
            $this->profile(),
            [FetchMethod::Http, FetchMethod::Flaresolverr],
            type: CrawlType::Detail,
        );

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::Flaresolverr, $result->methodUsed);
        self::assertCount(2, $result->attempts);
    }

    private function chain(): FetchFallbackChain
    {
        $runtime = $this->runtime();
        $runner = new ProcOpenProcessRunner();
        $browserRunner = new BrowserServiceProcessRunner($this->playwrightUrl());
        $cookies = new CookieHandoffStore();

        return new FetchFallbackChain([
            FetchMethod::Http->value => new HttpFetchHandler(new ClientFactory(), $cookies),
            FetchMethod::Playwright->value => new PlaywrightFamilyFetchHandler($runtime, $browserRunner),
            FetchMethod::Flaresolverr->value => new FlaresolverrFetchHandler($runtime),
        ], $cookies);
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'fetch-lab',
            displayName: 'Fetch Lab',
            baseUrl: $this->fixtureUrl('/'),
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http, FetchMethod::Playwright, FetchMethod::Flaresolverr],
            http: new HttpProfileDto(timeout: 20),
            playwright: new PlaywrightProfileDto(postWaitMs: 500, navigationTimeoutMs: 10_000),
            readyMarkers: [
                'listing' => ['#movie'],
                'detail' => ['#movie'],
            ],
            soft404Markers: ['page not found'],
        );
    }

    private function hangOptions(): CrawlOptionsDto
    {
        return new CrawlOptionsDto(
            http: new HttpOptionsDto(timeout: 1),
            fetch: new FetchOptionsDto(deadlineSeconds: 1),
        );
    }

    private function runtime(): FetchRuntimeConfig
    {
        $root = dirname(__DIR__, 3);

        return new FetchRuntimeConfig(
            playwrightScript: (string) (getenv('CRAWLERX_PLAYWRIGHT_SCRIPT') ?: $root . '/scripts/playwright-fetch.mjs'),
            puppeteerScript: (string) (getenv('CRAWLERX_PUPPETEER_SCRIPT') ?: $root . '/scripts/puppeteer-stealth-fetch.mjs'),
            flaresolverrUrl: (string) (getenv('FLARESOLVERR_URL') ?: $this->fixtureUrl('/__flare/v1')),
            playwrightUrl: $this->playwrightUrl(),
        );
    }

    private function fixtureUrl(string $path): string
    {
        $default = getenv('CI') === 'true' ? 'http://fixture-site:8080' : 'http://127.0.0.1:8080';

        return rtrim((string) (getenv('CRAWLERX_FIXTURE_SITE_URL') ?: $default), '/') . $path;
    }

    private function playwrightUrl(): string
    {
        return (string) (getenv('PLAYWRIGHT_URL') ?: 'http://127.0.0.1:3000');
    }
}
