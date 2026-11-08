<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Eporner;

use JOOservices\CrawlerX\Adapters\Eporner\Types\Gallery;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_every_photo_keeps_the_original_and_the_thumbnail_but_not_the_recommended_galleries(): void
    {
        $gallery = $this->item(Gallery::class, 'eporner/gallery-sample.html', 'https://www.eporner.com/gallery/xKeoFe7VHmO/Iori-Kogawa/', CrawlType::Gallery)->meta['gallery'];

        self::assertCount(77, $this->column($gallery['photos'], 'image_url'));
        self::assertCount(77, $this->column($gallery['photos'], 'thumbnail_url'));
        self::assertCount(77, array_unique($this->column($gallery['photos'], 'image_url')));
        self::assertSame('https://static-ca-cdn.eporner.com/gallery/mO/VH/xKeoFe7VHmO/30766987-mg-4472.jpg', $gallery['photos'][0]['image_url']);
        self::assertSame('https://static-ca-cdn.eporner.com/gallery/mO/VH/xKeoFe7VHmO/30766987-mg-4472_296x1000.jpg', $gallery['photos'][0]['thumbnail_url']);
        self::assertSame([], array_filter($this->column($gallery['photos'], 'image_url'), static fn(string $url): bool => ! str_contains($url, '/xKeoFe7VHmO/')));
    }
}
