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

final class OnePondoRouteCrawlTest extends CrawlerXTestCase
{
    private const LIST_ENDPOINT = 'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_weekly_0.json';

    private const LIST_PAGE_TWO_ENDPOINT = 'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_weekly_50.json';

    private const DETAIL_ENDPOINT = 'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json';

    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_weekly_listing_page_two_uses_live_json_payload(): void
    {
        FixtureResponder::for('GET', 'https://en.1pondo.tv/list/?o=weekly')->file('onepondo/list-newest-0.json');
        FixtureResponder::for('GET', self::LIST_ENDPOINT)->file('onepondo/list-newest-0.json');
        FixtureResponder::for('GET', self::LIST_PAGE_TWO_ENDPOINT)->file('onepondo/list-newest-0.json');

        $result = CrawlerX::site('onepondo')
            ->url('https://en.1pondo.tv/list/?o=weekly')
            ->type(CrawlType::Listing)
            ->page(2)
            ->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertNotEmpty($result->items);
    }

    public function test_public_movie_url_resolves_detail_live_json_payload(): void
    {
        FixtureResponder::for('GET', 'https://en.1pondo.tv/movies/060426_001/')->file('onepondo/detail-060426_001.json');
        FixtureResponder::for('GET', self::DETAIL_ENDPOINT)->file('onepondo/detail-060426_001.json');

        $result = CrawlerX::site('onepondo')
            ->url('https://en.1pondo.tv/movies/060426_001/')
            ->type(CrawlType::Detail)
            ->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        $movie = $result->meta['movie'] ?? null;
        self::assertIsArray($movie);
        self::assertSame('060426_001', $movie['external_id'] ?? null);
    }
}
