<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Xcity\Types\Detail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\Listing;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerIndex;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerKana;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerListing;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class XcityAdapterTest extends TestCase
{
    public function test_performer_detail_parses_profile_fixture(): void
    {
        $item = (new PerformerDetail($this->clientWithFixture('xcity/detail-performer-5517.html')))->execute(new CrawlRequestDto(
            url: 'https://xxx.xcity.jp/idol/detail/5517/',
            type: CrawlType::PerformerDetail,
        ));

        self::assertSame('5517', $item->meta['performer']['external_id']);
        self::assertSame('Ai Uehara', $item->meta['performer']['name']);
        self::assertNotEmpty($item->meta['performer']['raw_profile']);
    }

    public function test_detail_parses_movie_fields_and_full_size_screenshots(): void
    {
        $client = $this->clientWithFixture('xcity/detail-placeholder.html');
        $item = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://xxx.xcity.jp/avod/detail/?id=186022',
            type: CrawlType::Detail,
        ));

        $movie = $item->meta['movie'];
        self::assertSame('186022', $movie['external_id']);
        self::assertSame('Super Masochistic Female Teachers Who Being Dominated, 4 Hours', $movie['title']);
        self::assertSame('MXDLP0185', $movie['code']);
        self::assertSame(243, $movie['duration']);
        self::assertSame('https://faws.xcity.jp/package-b/large/image/maker/maxing/mxdlp0185/f_1707449901_1.jpg', $movie['cover_url']);
        self::assertSame('Tsubomi', $movie['performers'][0]['name']);
        self::assertSame('Nanako Mori', $movie['performers'][1]['name']);
        self::assertContains('Amateur/Plan', $movie['tags']);
        self::assertContains('Lewd', $movie['tags']);
        self::assertGreaterThanOrEqual(2, count($movie['screenshots']));
        self::assertSame('https://faws.xcity.jp/scene/small/image/maker/maxing/mxdlp0185/s_1707449951_1.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame('https://faws.xcity.jp/scene/large/image/maker/maxing/mxdlp0185/s_1707449951_1.jpg', $movie['screenshots'][0]['url']);
        self::assertGreaterThanOrEqual(2, count($movie['metadata']['cast_links']));
    }

    public function test_performer_index_returns_kana_discovery_urls(): void
    {
        $client = $this->clientWithFixture('xcity/listing-idol-index.html');
        $list = (new PerformerIndex($client))->execute(new CrawlRequestDto(
            url: 'https://xxx.xcity.jp/idol/',
            type: CrawlType::PerformerListing,
        ));

        self::assertGreaterThanOrEqual(10, count($list->items));
        $urls = array_map(fn(CrawlItemResultDto $item): string => $item->url, $list->items);
        self::assertContains('https://xxx.xcity.jp/idol/?kana=%E3%81%82', $urls);
        self::assertContains('https://xxx.xcity.jp/idol/?kana=%E3%81%8B', $urls);
    }

    public function test_performer_kana_returns_ini_discovery_urls(): void
    {
        $client = $this->clientWithFixture('xcity/listing-idol-kana-a.html');
        $list = (new PerformerKana($client))->execute(new CrawlRequestDto(
            url: 'https://xxx.xcity.jp/idol/?kana=%E3%81%82',
            type: CrawlType::PerformerListing,
        ));

        self::assertCount(5, $list->items);
        $urls = array_map(fn(CrawlItemResultDto $item): string => $item->url, $list->items);
        self::assertContains('https://xxx.xcity.jp/idol/?ini=%E3%81%82&num=100', $urls);
        self::assertContains('https://xxx.xcity.jp/idol/?ini=%E3%81%8A&num=100', $urls);
    }

    public function test_performer_listing_returns_detail_urls_and_pagination(): void
    {
        $client = $this->clientWithFixture('xcity/listing-performer-ini-a.html');
        $list = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://xxx.xcity.jp/idol/?ini=%E3%81%82&num=100',
            type: CrawlType::PerformerListing,
        ));

        self::assertCount(100, $list->items);
        $item = null;
        foreach ($list->items as $entry) {
            if (($entry->meta['performer']['external_id'] ?? null) === '5628') {
                $item = $entry;
                break;
            }
        }
        self::assertNotNull($item);
        self::assertSame('https://xxx.xcity.jp/idol/detail/5628/', $item->url);
        self::assertSame('Shunka Ayami', $item->meta['performer']['name']);
        self::assertTrue($list->pagination->hasNextPage);
        self::assertSame(2, $list->pagination->nextPage);
    }

    public function test_listing_parses_items_and_pagination(): void
    {
        $client = $this->clientWithFixture('xcity/listing_page1.html');
        $list = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://xxx.xcity.jp/avod/maker/hot/list/?style=simple&num=30',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertNotEmpty($list->items);
        self::assertTrue($list->pagination->hasNextPage);
        self::assertSame(2, $list->pagination->nextPage);
    }
}
