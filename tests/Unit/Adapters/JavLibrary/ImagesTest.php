<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavLibrary;

use JOOservices\CrawlerX\Adapters\JavLibrary\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_the_preview_thumbnails_are_kept_with_their_full_size_stills(): void
    {
        $movie = $this->item(Detail::class, 'JavLibrary/detail-javme3tuqi-rated.html', 'https://www.javlibrary.com/en/?v=javme3tuqi', CrawlType::Detail)->meta['movie'];

        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/hmn911/hmn911pl.jpg', $movie['cover_url']);
        self::assertCount(10, $this->column($movie['screenshots'], 'url'));
        self::assertCount(10, $this->column($movie['screenshots'], 'thumbnail_url'));
        self::assertSame('https://pics.dmm.co.jp/digital/video/hmn00911/hmn00911jp-1.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://pics.dmm.co.jp/digital/video/hmn00911/hmn00911-1.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame('https://pics.dmm.co.jp/digital/video/hmn00911/hmn00911jp-10.jpg', $movie['screenshots'][9]['url']);
    }

    public function test_a_video_without_previews_keeps_only_its_cover(): void
    {
        $movie = $this->item(Detail::class, 'JavLibrary/detail-javme3rasy-live.html', 'https://www.javlibrary.com/en/?v=javme3rasy', CrawlType::Detail)->meta['movie'];

        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/h_955kv328/h_955kv328pl.jpg', $movie['cover_url']);
        self::assertSame([], $movie['screenshots']);
    }

    public function test_every_listing_card_keeps_its_cover(): void
    {
        $result = $this->list(Listing::class, 'JavLibrary/listing-live.html', 'https://www.javlibrary.com/en/vl_newrelease.php', CrawlType::Listing);

        self::assertCount(20, $this->column($this->metaOf($result->items, 'movie'), 'cover_url'));
    }
}
