<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Warashi;

use JOOservices\CrawlerX\Adapters\Warashi\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const HOST = 'https://warashi-asian-pornstars.fr';

    public function test_the_gallery_keeps_every_large_photo_with_its_thumbnail_the_preview_and_the_performer_photo(): void
    {
        $gallery = $this->item(Gallery::class, 'warashi/gallery-yuna-ishikawa.html', self::HOST . '/en/s-7-0/yuna-ishikawa/female-asian-pornstar/photo-gallery/56092', CrawlType::Gallery)->meta['gallery'];

        self::assertCount(9, $this->column($gallery['photos'], 'image_url'));
        self::assertCount(9, $this->column($gallery['photos'], 'thumbnail_url'));
        self::assertStringContainsString('/large/', $gallery['photos'][0]['image_url']);
        self::assertStringContainsString('/mini/', $gallery['photos'][0]['thumbnail_url']);
        self::assertSame(
            'http://warashi-asian-pornstars.fr/WAPdB-img/pornostars-f-galeries/56000/56092/wapdb-yuna-ishikawa-pornostar-asiatique.warashi-asian-pornstars.fr-56092-preview.jpg',
            $gallery['metadata']['cover_url'],
        );
        self::assertSame(
            self::HOST . '/WAPdB-img/pornostars-f/y/u/3490/yuna-ishikawa/preview/mini/wapdb-yuna-ishikawa-pornostar-asiatique.warashi-asian-pornstars.fr.jpg',
            $gallery['metadata']['performer_image_url'],
        );
        self::assertStringContainsString('/contenus-web/', $gallery['metadata']['related_content']['cover_url']);
    }

    public function test_every_performer_card_keeps_its_portrait(): void
    {
        $result = $this->list(PerformerListing::class, 'warashi/listing_page1.html', self::HOST . '/en/s-2-2/female-pornstars/toutes/all/page/1', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertCount(36, $performers);
        self::assertCount(36, $this->column($performers, 'profile_image_url'));
    }

    public function test_the_performer_page_keeps_the_large_portrait_the_gallery_previews_and_the_film_cover(): void
    {
        $performer = $this->item(PerformerDetail::class, 'warashi/detail_yua_mikami.html', self::HOST . '/en/s-2-0/yua-mikami/asian-female-pornstar/1234', CrawlType::PerformerDetail)->meta['performer'];

        self::assertStringContainsString('/profil-0/large/', $performer['profile_image_url']);
        self::assertCount(2, $this->column($performer['metadata']['photo_galleries'], 'preview_url'));
        self::assertCount(1, $this->column($performer['metadata']['filmography'], 'cover_url'));
        self::assertStringNotContainsString('/design/', $performer['profile_image_url'], 'the lazy placeholders are not photos');
    }
}
