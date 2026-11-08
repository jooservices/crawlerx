<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Xcity;

use JOOservices\CrawlerX\Adapters\Xcity\Types\Detail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\Listing;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerIndex;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerKana;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_the_movie_page_keeps_the_large_cover_and_every_scene_pair(): void
    {
        $movie = $this->item(Detail::class, 'xcity/detail-208587.html', 'https://xcity.jp/avod/detail/?id=208587', CrawlType::Detail)->meta['movie'];

        self::assertSame('https://faws.xcity.jp/package-b/large/image/maker/hot/hez888/b_1788487793_1.jpg', $movie['cover_url']);
        self::assertCount(10, $this->column($movie['screenshots'], 'url'));
        self::assertCount(10, $this->column($movie['screenshots'], 'thumbnail_url'));
        self::assertSame('https://faws.xcity.jp/scene/large/image/maker/hot/hez888/s_1777531084_1.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://faws.xcity.jp/scene/small/image/maker/hot/hez888/s_1777531084_1.jpg', $movie['screenshots'][0]['thumbnail_url']);
    }

    public function test_every_listing_card_keeps_the_small_and_the_large_cover(): void
    {
        $result = $this->list(Listing::class, 'xcity/listing_page1.html', 'https://xxx.xcity.jp/avod/list/', CrawlType::Listing);
        $movies = $this->metaOf($result->items, 'movie');

        self::assertCount(30, $this->column($movies, 'cover_url'));
        self::assertCount(30, $this->column(array_column($movies, 'metadata'), 'cover_large_url'));
    }

    public function test_the_performer_page_keeps_the_portrait_and_the_filmography_covers(): void
    {
        $performer = $this->item(PerformerDetail::class, 'xcity/performer-detail-10696.html', 'https://xcity.jp/idol/detail/10696/', CrawlType::PerformerDetail)->meta['performer'];

        self::assertSame('https://faws.xcity.jp/actress/large/image/person/thumb_1497583070.jpg', $performer['profile_image_url']);
        self::assertCount(20, $this->column($performer['metadata']['filmography'], 'cover_url'));
    }

    public function test_every_performer_card_keeps_its_portrait(): void
    {
        $result = $this->list(PerformerListing::class, 'xcity/performer-listing-a.html', 'https://xxx.xcity.jp/idol/?ini=%E3%81%82&num=100', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertCount(100, $performers);
        self::assertCount(100, $this->column($performers, 'profile_image_url'));
        self::assertSame('https://faws.xcity.jp/actress/large/image/person/thumb_1518577517.jpg', $performers[0]['profile_image_url']);
    }

    public function test_the_discovery_pages_only_hand_out_urls_and_carry_no_entity_images(): void
    {
        $index = $this->list(PerformerIndex::class, 'xcity/performer-index.html', 'https://xxx.xcity.jp/idol/', CrawlType::PerformerListing);
        $kana = $this->list(PerformerKana::class, 'xcity/performer-kana-a.html', 'https://xxx.xcity.jp/idol/?kana=%E3%81%82', CrawlType::PerformerListing);

        foreach ([...$index->items, ...$kana->items] as $item) {
            self::assertSame('performer_listing', $item->nextCrawlType);
            self::assertNull($item->meta['performer']['profile_image_url']);
        }
    }
}
