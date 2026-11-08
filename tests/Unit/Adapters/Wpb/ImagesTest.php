<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Wpb;

use JOOservices\CrawlerX\Adapters\Wpb\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Wpb\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const GALLERY = 'https://wpb.shueisha.co.jp/gravure/20261108-133432/';

    private const COVER = 'https://wpb.shueisha.co.jp/gravure/2026/images/02d3b86d4863a35e996596f06c88615d5e15b841.jpg';

    public function test_gallery_keeps_the_work_cover_at_full_resolution_and_skips_chrome(): void
    {
        $gallery = $this->item(Gallery::class, 'wpb/gallery-20261108-133432.html', self::GALLERY, CrawlType::Gallery)
            ->meta['gallery'];
        $urls = $this->column($gallery['photos'], 'image_url');

        self::assertSame([self::COVER], $urls);
        self::assertSame(self::COVER, $gallery['metadata']['cover']['url']);
        self::assertNotEmpty($gallery['metadata']['cover']['thumbnail_url']);
        self::assertStringNotContainsString('/images/common/', implode(' ', $urls));
        self::assertStringNotContainsString('4375d21660a17e6b0e50d5402d16cb885ab42bd1', implode(' ', $urls));
        self::assertStringNotContainsString('item-ranking', implode(' ', $urls));
    }

    public function test_every_listing_card_keeps_cover_and_thumbnail(): void
    {
        $result = $this->list(Listing::class, 'wpb/listing-page-1.html', 'https://wpb.shueisha.co.jp/gravure/list/', CrawlType::Listing);
        $covers = array_column(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), 'cover');

        self::assertCount(24, $this->column($covers, 'url'));
        self::assertCount(24, $this->column($covers, 'thumbnail_url'));
        self::assertSame(self::COVER, $covers[0]['url']);
    }
}
