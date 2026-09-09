<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\UnsupportedUrlException;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class CrawlerXFacadeTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_site_then_url_crawls_detail(): void
    {
        $url = 'https://onejav.com/torrent/ymds282';
        FixtureResponder::for('GET', $url)->file('onejav/detail-sample-1.html');

        $item = CrawlerX::site('onejav')->url($url)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $item);
        self::assertSame('YMDS-282', $item->meta['movie']['code']);
    }

    public function test_try_crawl_returns_unsupported_url(): void
    {
        $outcome = CrawlerX::url('https://example.invalid/unknown')->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertSame(CrawlErrorCode::UnsupportedUrl, $outcome->error?->code);
    }

    public function test_crawl_throws_on_unsupported_url(): void
    {
        $this->expectException(UnsupportedUrlException::class);
        CrawlerX::url('https://example.invalid/unknown')->crawl();
    }

    public function test_http_only_fetch_profile_override_still_parses_listing(): void
    {
        $url = 'https://onejav.com/new';
        FixtureResponder::for('GET', $url)->file('onejav/listing-page-1.html');

        $list = CrawlerX::url($url)
            ->options(new CrawlOptionsDto(fetch: new FetchOptionsDto(profile: FetchProfile::HttpOnly)))
            ->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $list);
        self::assertNotEmpty($list->items);
    }

    public function test_explicit_type_listing(): void
    {
        $url = 'https://onejav.com/new';
        FixtureResponder::for('GET', $url)->file('onejav/listing-page-1.html');

        $list = CrawlerX::url($url)->type(CrawlType::Listing)->page(1)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $list);
    }

    public function test_try_crawl_parse_failure_maps_blocked_challenge(): void
    {
        $url = 'https://missav.to/latest-updates';
        FixtureResponder::for('GET', $url)->file('missav/cloudflare.html', 403, ['cf-mitigated' => 'challenge']);

        $outcome = CrawlerX::url($url)->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertContains($outcome->error?->code, [CrawlErrorCode::Blocked, CrawlErrorCode::ParseFailed]);
    }
}
