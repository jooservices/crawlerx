<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\FetchChainDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\FetchPlanResolver;
use PHPUnit\Framework\TestCase;

final class FetchPlanResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_http_only_site_uses_http_chain(): void
    {
        $plan = (new FetchPlanResolver())->resolve($this->profile(false));

        self::assertSame([FetchMethod::Http], $plan);
    }

    public function test_browser_likely_site_starts_at_playwright(): void
    {
        $plan = (new FetchPlanResolver())->resolve($this->profile(true));

        self::assertSame(FetchMethod::Playwright, $plan[0]);
        self::assertContains(FetchMethod::ChromeStealth, $plan);
        self::assertNotContains(FetchMethod::Http, $plan);
    }

    public function test_explicit_chain_wins(): void
    {
        $plan = (new FetchPlanResolver())->resolve(
            $this->profile(true),
            new FetchOptionsDto(chain: new FetchChainDto([FetchMethod::Playwright, FetchMethod::Flaresolverr])),
        );

        self::assertSame([FetchMethod::Playwright, FetchMethod::Flaresolverr], $plan);
    }

    public function test_method_without_fallback_is_single_handler(): void
    {
        $plan = (new FetchPlanResolver())->resolve(
            $this->profile(true),
            new FetchOptionsDto(method: FetchMethod::ChromeStealth, noFallback: true),
        );

        self::assertSame([FetchMethod::ChromeStealth], $plan);
    }

    public function test_method_starts_at_handler_then_rest_of_base_chain(): void
    {
        $plan = (new FetchPlanResolver())->resolve(
            $this->profile(true),
            new FetchOptionsDto(method: FetchMethod::ChromeStealth),
        );

        self::assertSame(FetchMethod::ChromeStealth, $plan[0]);
        self::assertContains(FetchMethod::Flaresolverr, $plan);
    }

    public function test_named_profile_replaces_site_chain(): void
    {
        $plan = (new FetchPlanResolver())->resolve(
            $this->profile(true),
            new FetchOptionsDto(profile: FetchProfile::HttpOnly),
        );

        self::assertSame([FetchMethod::Http], $plan);
    }

    public function test_client_fake_forces_http_only(): void
    {
        ClientBuilder::fake();
        $plan = (new FetchPlanResolver())->resolve($this->profile(true));

        self::assertSame([FetchMethod::Http], $plan);
    }

    private function profile(bool $browser): SiteProfileDto
    {
        return SiteProfileDto::fromManifest(new AdapterManifestDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            adapterClass: \JOOservices\CrawlerX\Adapters\Onejav\OnejavCrawler::class,
            pagination: 'query',
            capabilities: ['listing', 'detail'],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: ['timeout' => 30],
            playwrightFetchEnabled: $browser,
        ));
    }
}
