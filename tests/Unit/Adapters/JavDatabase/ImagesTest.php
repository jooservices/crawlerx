<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavDatabase;

use JOOservices\CrawlerX\Adapters\JavDatabase\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavDatabase\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_every_performer_card_keeps_its_portrait(): void
    {
        $result = $this->list(PerformerListing::class, 'javdatabase/listing_page1.html', 'https://www.javdatabase.com/idols/', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertCount(18, $performers);
        self::assertCount(18, $this->column($performers, 'profile_image_url'));
        self::assertSame('https://www.javdatabase.com/idolimages/thumb/mochi-azuki.webp', $performers[0]['profile_image_url']);
    }

    public function test_the_performer_page_keeps_the_portrait_the_filmography_covers_and_the_lightbox_stills(): void
    {
        $performer = $this->item(PerformerDetail::class, 'javdatabase/detail_yua_mikami.html', 'https://www.javdatabase.com/idols/yua-mikami/', CrawlType::PerformerDetail)->meta['performer'];

        self::assertSame('https://www.javdatabase.com/idolimages/full/yua-mikami.webp', $performer['profile_image_url']);
        self::assertCount(18, $this->column($performer['metadata']['filmography'], 'cover_url'));

        $gallery = $performer['metadata']['gallery_images'];
        self::assertCount(54, $gallery);
        self::assertCount(54, $this->column($gallery, 'thumbnail_url'));
        self::assertSame('https://pics.dmm.co.jp/digital/video/ofje00443/ofje00443jp-1.jpg', $gallery[0]['url']);
        self::assertSame('https://pics.dmm.co.jp/digital/video/ofje00443/ofje00443-1.jpg', $gallery[0]['thumbnail_url']);
        self::assertSame('OFJE-443', $gallery[0]['movie_code']);
        self::assertSame('https://www.javdatabase.com/movies/ofje-443/', $gallery[0]['movie_url']);
        self::assertCount(54, array_unique($this->column($gallery, 'url')), 'de-duplicated');
    }
}
