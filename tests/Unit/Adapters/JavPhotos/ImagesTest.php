<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavPhotos;

use JOOservices\CrawlerX\Adapters\JavPhotos\Types\Gallery;
use JOOservices\CrawlerX\Adapters\JavPhotos\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const SLUG = '1pondo-hana-aoyama-sexist-mobile-pics';

    public function test_the_gallery_keeps_every_photo_with_its_thumbnail_but_not_the_related_galleries(): void
    {
        $gallery = $this->item(Gallery::class, 'javphotos/gallery-hana-aoyama.html', 'https://jav.photos/free/' . self::SLUG, CrawlType::Gallery)->meta['gallery'];

        self::assertCount(24, $this->column($gallery['photos'], 'image_url'));
        self::assertCount(24, $this->column($gallery['photos'], 'thumbnail_url'));
        self::assertCount(24, array_unique($this->column($gallery['photos'], 'image_url')));
        self::assertStringContainsString('/hana-aoyama/', implode(' ', $this->column($gallery['photos'], 'image_url')));
        self::assertStringNotContainsString('maki-houjou', implode(' ', $this->column($gallery['photos'], 'image_url')));
    }

    public function test_every_listing_card_keeps_its_thumbnail(): void
    {
        $result = $this->list(Listing::class, 'javphotos/listing-page1.html', 'https://jav.photos/free/', CrawlType::Listing);

        self::assertCount(60, array_filter(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), static fn(array $metadata): bool => is_string($metadata['thumbnail_url'] ?? null)));
    }
}
