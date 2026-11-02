<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class MuramuraRouteCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_movie_page_url_is_inferred_as_detail_without_site_or_type(): void
    {
        FixtureResponder::for('GET', 'https://www.muramura.tv/movies/103126_1273/')
            ->file('muramura/detail-103126_1273.json');
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_details/movie_id/103126_1273.json')
            ->file('muramura/detail-103126_1273.json');
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_galleries/movie_id/103126_1273.json')
            ->body('<!doctype html><title>Not Found</title>', 404, ['Content-Type' => 'text/html']);

        $result = CrawlerX::url('https://www.muramura.tv/movies/103126_1273/')->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('103126_1273', $result->meta['movie']['external_id']);
    }

    public function test_listing_page_two_uses_the_site_split_size_and_total_rows(): void
    {
        $fixture = 'muramura/list-newest-0.json';
        FixtureResponder::for('GET', 'https://www.muramura.tv/list/?o=n&page=2')->file($fixture);
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_lists/list_newest_0.json')->file($fixture);
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_lists/list_newest_50.json')->file($fixture);

        $result = CrawlerX::url('https://www.muramura.tv/list/?o=n&page=2')->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(35, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame('103126_1273', $result->items[0]->meta['movie']['external_id']);
    }

    public function test_list_route_is_inferred_without_site_or_type(): void
    {
        FixtureResponder::for('GET', 'https://www.muramura.tv/list/?o=n')->file('muramura/list-newest-0.json');
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_lists/list_newest_0.json')
            ->file('muramura/list-newest-0.json');

        $result = CrawlerX::url('https://www.muramura.tv/list/?o=n')->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }

    public function test_bare_host_is_inferred_without_site_or_type(): void
    {
        FixtureResponder::for('GET', 'https://muramura.tv/list/?o=n')->file('muramura/list-newest-0.json');
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_lists/list_newest_0.json')
            ->file('muramura/list-newest-0.json');

        $result = CrawlerX::url('https://muramura.tv/list/?o=n')->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }
}
