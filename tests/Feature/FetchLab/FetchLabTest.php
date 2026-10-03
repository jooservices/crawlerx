<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\FetchLab;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PuppeteerStealthFetchHandler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class FetchLabTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('CRAWLERX_FETCH_LAB') !== '1') {
            self::markTestSkipped('Set CRAWLERX_FETCH_LAB=1 to run the Compose fetch lab.');
        }
    }

    public function test_tc_m01_http_fetches_static_fixture(): void
    {
        $result = (new HttpFetchHandler(new ClientFactory()))->fetch(
            $this->fixtureUrl('/static/movie/TC-M01'),
            $this->profile(),
            FetchMethod::Http,
        );

        self::assertTrue($result->ok, (string) $result->error);
        self::assertStringContainsString('static movie', $result->body);
    }

    public function test_tc_m02_playwright_renders_javascript_fixture(): void
    {
        $result = (new PlaywrightFamilyFetchHandler(
            $this->runtime(),
            new BrowserServiceProcessRunner($this->browserServiceUrl()),
        ))->fetch(
            $this->fixtureUrl('/js/movie/TC-M02'),
            $this->profile(playwright: new PlaywrightProfileDto(postWaitMs: 750)),
            FetchMethod::Playwright,
        );

        self::assertTrue($result->ok, (string) $result->error);
        self::assertStringContainsString('js movie TC-M02', $result->body);
    }

    public function test_tc_m03_playwright_stealth_passes_browser_user_agent_gate(): void
    {
        $result = (new PlaywrightFamilyFetchHandler(
            $this->runtime(),
            new BrowserServiceProcessRunner($this->browserServiceUrl()),
        ))->fetch(
            $this->fixtureUrl('/ua-check/TC-M03'),
            $this->profile(),
            FetchMethod::PlaywrightStealth,
        );

        self::assertTrue($result->ok, (string) $result->error);
        self::assertStringContainsString('ua accepted', $result->body);
    }

    public function test_tc_m04_puppeteer_stealth_renders_javascript_fixture(): void
    {
        $result = (new PuppeteerStealthFetchHandler(
            $this->runtime(),
            new BrowserServiceProcessRunner($this->browserServiceUrl()),
        ))->fetch(
            $this->fixtureUrl('/js/movie/TC-M04'),
            $this->profile(playwright: new PlaywrightProfileDto(postWaitMs: 750)),
            FetchMethod::PuppeteerStealth,
        );

        self::assertTrue($result->ok, (string) $result->error);
        self::assertStringContainsString('js movie TC-M04', $result->body);
    }

    public function test_tc_m05_flaresolverr_fetches_static_fixture(): void
    {
        $result = (new FlaresolverrFetchHandler($this->runtime()))->fetch(
            $this->fixtureUrl('/static/movie/TC-M05'),
            $this->profile(),
            FetchMethod::Flaresolverr,
        );

        self::assertTrue($result->ok, (string) $result->error);
        self::assertStringContainsString('static movie', $result->body);
    }

    public function test_tc_m06_http_sends_the_legal_age_cookie(): void
    {
        $result = (new HttpFetchHandler(new ClientFactory()))->fetch(
            $this->fixtureUrl('/age-gate/TC-M06'),
            $this->profile(headers: ['Cookie' => 'legal_age=1']),
            FetchMethod::Http,
        );

        self::assertTrue($result->ok, (string) $result->error);
        self::assertStringContainsString('age accepted', $result->body);
    }

    public function test_tc_m07_playwright_reports_the_fixture_challenge(): void
    {
        $result = (new PlaywrightFamilyFetchHandler(
            $this->runtime(),
            new BrowserServiceProcessRunner($this->browserServiceUrl()),
        ))->fetch(
            $this->fixtureUrl('/challenge'),
            $this->profile(),
            FetchMethod::Playwright,
        );

        self::assertFalse($result->ok);
        self::assertTrue($result->challengeDetected);
    }

    public function test_tc_m08_http_propagates_configured_user_agent_and_cookie_headers(): void
    {
        $handler = new HttpFetchHandler(new ClientFactory());
        $profile = $this->profile(headers: [
            'User-Agent' => 'CrawlerX-Lab Chrome/1.0',
            'Cookie' => 'legal_age=1',
        ]);

        $userAgent = $handler->fetch(
            $this->fixtureUrl('/ua-check/TC-M08-UA'),
            $profile,
            FetchMethod::Http,
        );
        $cookie = $handler->fetch(
            $this->fixtureUrl('/age-gate/TC-M08-Cookie'),
            $profile,
            FetchMethod::Http,
        );

        self::assertTrue($userAgent->ok, (string) $userAgent->error);
        self::assertStringContainsString('ua accepted', $userAgent->body);
        self::assertTrue($cookie->ok, (string) $cookie->error);
        self::assertStringContainsString('age accepted', $cookie->body);
    }

    private function fixtureUrl(string $path): string
    {
        $default = getenv('CI') === 'true' ? 'http://fixture-site:8080' : 'http://127.0.0.1:8080';
        return rtrim((string) (getenv('CRAWLERX_FIXTURE_SITE_URL') ?: $default), '/') . $path;
    }

    private function browserServiceUrl(): string
    {
        return (string) (getenv('CRAWLERX_BROWSER_SERVICE_URL') ?: 'http://127.0.0.1:3000');
    }

    private function runtime(): FetchRuntimeConfig
    {
        $root = dirname(__DIR__, 3);

        return new FetchRuntimeConfig(
            nodeBinary: (string) (getenv('CRAWLERX_NODE') ?: 'node'),
            playwrightScript: (string) (getenv('CRAWLERX_PLAYWRIGHT_SCRIPT') ?: $root . '/scripts/playwright-fetch.mjs'),
            puppeteerScript: (string) (getenv('CRAWLERX_PUPPETEER_SCRIPT') ?: $root . '/scripts/puppeteer-stealth-fetch.mjs'),
            flaresolverrUrl: (string) (getenv('CRAWLERX_FLARESOLVERR_URL') ?: 'http://127.0.0.1:8191/v1'),
            browserServiceUrl: $this->browserServiceUrl(),
        );
    }

    /** @param array<string, string> $headers */
    private function profile(array $headers = [], ?PlaywrightProfileDto $playwright = null): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'fetch-lab',
            displayName: 'Fetch Lab',
            baseUrl: $this->fixtureUrl('/'),
            fetchProfile: FetchProfile::BrowserLikely,
            fetchChain: FetchMethod::browserChain(),
            http: new HttpProfileDto(headers: $headers),
            playwright: $playwright,
        );
    }
}
