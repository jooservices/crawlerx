<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Asagei;

use JOOservices\CrawlerX\Adapters\Asagei\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Asagei\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const LISTING = 'https://www.asagei.com/tag/%E3%82%B0%E3%83%A9%E3%83%93%E3%82%A2';

    private const ARTICLE = 'https://www.asagei.com/excerpt/349499';

    private const COVER = 'https://cdn.asagei.com/asagei/uploads/2026/09/ap-aoyama-20260914151510.jpg';

    private const COVER_THUMB = 'https://cdn.asagei.com/asagei/uploads/2026/09/ap-aoyama-20260914151510-150x150.jpg';

    public function test_gallery_keeps_every_article_image_and_cover(): void
    {
        $gallery = $this->item(Gallery::class, 'asagei/gallery-349499.html', self::ARTICLE, CrawlType::Gallery)
            ->meta['gallery'];

        self::assertCount(1, $this->column($gallery['photos'], 'image_url'));
        self::assertSame(self::COVER, $gallery['photos'][0]['image_url']);
        self::assertSame(self::COVER, $gallery['metadata']['cover']['url']);
        self::assertSame(1, $gallery['photo_count']);
    }

    public function test_every_listing_card_keeps_cover_and_thumbnail(): void
    {
        $result = $this->list(Listing::class, 'asagei/listing-gravure-page1.html', self::LISTING, CrawlType::Listing);
        $covers = array_column(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), 'cover');

        self::assertGreaterThanOrEqual(10, count($covers));
        self::assertCount(count($covers), $this->column($covers, 'url'));
        self::assertCount(count($covers), $this->column($covers, 'thumbnail_url'));
        self::assertSame(self::COVER, $covers[0]['url']);
        self::assertSame(self::COVER_THUMB, $covers[0]['thumbnail_url']);
    }
}
