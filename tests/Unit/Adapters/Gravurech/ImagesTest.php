<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Gravurech;

use JOOservices\CrawlerX\Adapters\Gravurech\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Gravurech\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_the_public_page_offers_one_photo_and_the_related_content_is_not_added(): void
    {
        foreach (['j6R' => '1713', '0bb' => '1710'] as $id => $contentId) {
            $gallery = $this->item(Gallery::class, 'gravurech/detail-' . $id . '.html', 'https://gravure-ch.com/content/' . $id, CrawlType::Gallery)->meta['gallery'];

            self::assertCount(1, $gallery['photos']);
            self::assertSame('https://gravure-ch.com/storage/newPhoto/' . $contentId . '_thumbnail1.jpg', $gallery['photos'][0]['image_url']);
        }
    }

    public function test_every_listing_card_keeps_its_thumbnail(): void
    {
        $result = $this->list(Listing::class, 'gravurech/listing-new-photo.html', 'https://gravure-ch.com/search/new/photo', CrawlType::Listing);
        $thumbnails = $this->column(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), 'thumbnail_url');

        self::assertCount(60, $thumbnails);
        self::assertCount(60, array_unique($thumbnails));
    }
}
