<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavBus;

use JOOservices\CrawlerX\Adapters\JavBus\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavBus\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_the_movie_page_keeps_cover_every_sample_pair_and_the_actress_photo(): void
    {
        $movie = $this->item(Detail::class, 'javbus/detail-namh-074-live.html', 'https://www.javbus.com/en/NAMH-074', CrawlType::Detail)->meta['movie'];

        self::assertSame('https://www.javbus.com/pics/cover/chuw_b.jpg', $movie['cover_url']);
        self::assertCount(17, $this->column($movie['screenshots'], 'url'));
        self::assertCount(17, $this->column($movie['screenshots'], 'thumbnail_url'));
        self::assertSame('https://www.javbus.com/pics/sample/chuw_17.jpg', $movie['screenshots'][16]['thumbnail_url']);
        self::assertSame('https://www.javbus.com/pics/actress/11p7_a.jpg', $movie['performers'][0]['profile_image_url']);
    }

    public function test_every_listing_card_keeps_its_cover_including_ids_with_an_underscore(): void
    {
        $result = $this->list(Listing::class, 'javbus/listing-live.html', 'https://www.javbus.com/en/', CrawlType::Listing);
        $movies = $this->metaOf($result->items, 'movie');

        self::assertCount(30, $movies);
        self::assertCount(30, $this->column($movies, 'cover_url'));
        $dated = array_values(array_filter($movies, static fn(array $movie): bool => $movie['external_id'] === 'SPSC-022_2026-09-08'));
        self::assertCount(1, $dated);
        self::assertStringStartsWith('https://www.javbus.com/pics/thumb/', $dated[0]['cover_url']);
    }

    public function test_the_performer_page_keeps_the_portrait_and_every_filmography_cover(): void
    {
        $performer = $this->item(PerformerDetail::class, 'javbus/performer-detail-11p7-live.html', 'https://www.javbus.com/en/star/11p7', CrawlType::PerformerDetail)->meta['performer'];

        self::assertSame('https://www.javbus.com/pics/actress/11p7_a.jpg', $performer['profile_image_url']);
        self::assertCount(29, $this->column($performer['metadata']['filmography'], 'cover_url'));
    }

    public function test_every_performer_card_keeps_the_avatar(): void
    {
        $result = $this->list(PerformerListing::class, 'javbus/performer-listing-live.html', 'https://www.javbus.com/en/actresses', CrawlType::PerformerListing);

        self::assertCount(50, $this->column($this->metaOf($result->items, 'performer'), 'profile_image_url'));
    }
}
