<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class OnejavCrawlTest extends CrawlerXTestCase
{
    public function test_detail_from_url_only(): void
    {
        $url = 'https://onejav.com/torrent/ymds282';
        FixtureResponder::for('GET', $url)->file('onejav/detail-sample-1.html');

        $item = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $item);
        self::assertSame('ymds282', $item->meta['movie']['external_id']);
        self::assertSame('YMDS-282', $item->meta['movie']['code']);
    }

    public function test_listing_from_url_only(): void
    {
        $url = 'https://onejav.com/new';
        FixtureResponder::for('GET', $url)->file('onejav/listing-page-1.html');

        $list = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $list);
        self::assertNotEmpty($list->items);
    }
}
