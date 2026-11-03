<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class PacopacomamaRouteCrawlTest extends CrawlerXTestCase
{
    private const BASE = 'https://www.pacopacomama.com';

    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_public_detail_url_detects_the_adapter_and_fetches_its_public_gallery(): void
    {
        $url = self::BASE . '/movies/110326_100/';
        FixtureResponder::for('GET', $url)->file('pacopacomama/detail-110326_100.json');
        FixtureResponder::for('GET', self::BASE . '/dyn/phpauto/movie_details/movie_id/110326_100.json')->file('pacopacomama/detail-110326_100.json');
        FixtureResponder::for('GET', self::BASE . '/dyn/dla/json/movie_gallery/110326_100.json')->file('pacopacomama/gallery-110326_100.json');

        $result = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame($url, $result->url);
        self::assertSame('110326_100', $result->meta['movie']['external_id']);
        self::assertCount(3, $result->meta['movie']['screenshots']);
    }

    public function test_public_listing_url_detects_the_adapter_and_page_number(): void
    {
        $url = self::BASE . '/list/?o=n&page=2';
        foreach ([$url, self::BASE . '/dyn/phpauto/movie_lists/list_newest_0.json', self::BASE . '/dyn/phpauto/movie_lists/list_newest_50.json'] as $endpoint) {
            FixtureResponder::for('GET', $endpoint)->file('pacopacomama/list-newest-0.json');
        }

        $result = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame(2, $result->page);
        self::assertCount(2, $result->items);
    }
    public function test_bare_host_is_inferred_without_site_or_type(): void
    {
        $url = 'https://pacopacomama.com/list/?o=n';
        FixtureResponder::for('GET', $url)->file('pacopacomama/list-newest-0.json');
        FixtureResponder::for('GET', self::BASE . '/dyn/phpauto/movie_lists/list_newest_0.json')->file('pacopacomama/list-newest-0.json');

        $result = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }
    public function test_direct_json_urls_preserve_listing_kind_offset_and_next_url(): void
    {
        foreach (['weekly' => 0, 'newest' => 50] as $kind => $offset) {
            CrawlerXFactory::reset();
            $url = self::BASE . '/dyn/phpauto/movie_lists/list_' . $kind . '_' . $offset . '.json';
            FixtureResponder::for('GET', $url)->file('pacopacomama/list-newest-0.json');

            $result = CrawlerX::url($url)->crawl();

            self::assertInstanceOf(CrawlListResultDto::class, $result);
            self::assertSame(intdiv($offset, 50) + 1, $result->page);
            self::assertSame($result->page, $result->pagination->currentPage);
            self::assertSame(self::BASE . '/dyn/phpauto/movie_lists/list_' . $kind . '_' . ($offset + 50) . '.json', $result->pagination->nextUrl);
        }
    }
}
