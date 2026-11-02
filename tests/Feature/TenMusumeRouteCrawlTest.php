<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class TenMusumeRouteCrawlTest extends CrawlerXTestCase
{
    private const LIST_ENDPOINT = 'https://www.10musume.com/dyn/phpauto/movie_lists/list_newest_0.json';

    private const LIST_PAGE_TWO_ENDPOINT = 'https://www.10musume.com/dyn/phpauto/movie_lists/list_newest_50.json';

    private const DETAIL_ENDPOINT = 'https://www.10musume.com/dyn/phpauto/movie_details/movie_id/110326_01.json';

    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_newest_listing_page_two_uses_live_json_payload(): void
    {
        FixtureResponder::for('GET', 'https://www.10musume.com/list/?o=n')->file('10musume/list-newest-0.json');
        FixtureResponder::for('GET', self::LIST_ENDPOINT)->file('10musume/list-newest-0.json');
        FixtureResponder::for('GET', self::LIST_PAGE_TWO_ENDPOINT)->file('10musume/list-newest-0.json');

        $result = CrawlerX::site('10musume')
            ->url('https://www.10musume.com/list/?o=n')
            ->type(CrawlType::Listing)
            ->page(2)
            ->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(69, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertNotEmpty($result->items);
        self::assertSame('110326_01', $result->items[0]->meta['movie']['external_id']);
    }

    public function test_public_movie_url_resolves_detail_live_json_payload(): void
    {
        FixtureResponder::for('GET', 'https://www.10musume.com/movies/110326_01/')->file('10musume/detail-110326_01.json');
        FixtureResponder::for('GET', self::DETAIL_ENDPOINT)->file('10musume/detail-110326_01.json');

        $result = CrawlerX::site('10musume')
            ->url('https://www.10musume.com/movies/110326_01/')
            ->type(CrawlType::Detail)
            ->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        $movie = $result->meta['movie'] ?? null;
        self::assertIsArray($movie);
        self::assertSame('110326_01', $movie['external_id'] ?? null);
    }

    public function test_public_list_route_is_inferred_as_listing_without_site_or_type(): void
    {
        FixtureResponder::for('GET', 'https://www.10musume.com/list/?o=n')->file('10musume/list-newest-0.json');
        FixtureResponder::for('GET', self::LIST_ENDPOINT)->file('10musume/list-newest-0.json');

        $result = CrawlerX::url('https://www.10musume.com/list/?o=n')->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }

    public function test_public_movie_url_is_inferred_as_detail_without_site_or_type(): void
    {
        FixtureResponder::for('GET', 'https://www.10musume.com/movies/110326_01/')->file('10musume/detail-110326_01.json');
        FixtureResponder::for('GET', self::DETAIL_ENDPOINT)->file('10musume/detail-110326_01.json');

        $result = CrawlerX::url('https://www.10musume.com/movies/110326_01/')->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('110326_01', $result->meta['movie']['external_id']);
    }

    public function test_bare_host_is_accepted_during_inference(): void
    {
        FixtureResponder::for('GET', 'https://10musume.com/list/?o=n')->file('10musume/list-newest-0.json');
        FixtureResponder::for('GET', self::LIST_ENDPOINT)->file('10musume/list-newest-0.json');

        $result = CrawlerX::url('https://10musume.com/list/?o=n')->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }
}
