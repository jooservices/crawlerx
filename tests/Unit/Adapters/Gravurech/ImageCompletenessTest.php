<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Gravurech;

use JOOservices\CrawlerX\Adapters\Gravurech\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Gravurech\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_each_public_detail_keeps_its_cover_without_related_work_images(): void
    {
        foreach ([
            ['detail-j6R.html', 'j6R'],
            ['detail-0bb.html', '0bb'],
        ] as [$fixtureName, $id]) {
            $fixture = 'gravurech/' . $fixtureName;
            $expected = (new Crawler($this->loadFixture($fixture)))->filter('.product-detail .thumbnail img')->count();
            $gallery = $this->item(
                Gallery::class,
                $fixture,
                'https://gravure-ch.com/content/' . $id,
                CrawlType::Gallery,
            )->meta['gallery'];

            self::assertSame(1, $expected, $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($gallery['photos']), $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column($gallery['photos'], 'image_url')), $fixtureName);
        }
    }

    public function test_listing_keeps_each_product_thumbnail(): void
    {
        $fixture = 'gravurech/listing-new-photo.html';
        $expected = (new Crawler($this->loadFixture($fixture)))->filter('.products__list > .product .thumbnail img')->count();
        $result = $this->list(Listing::class, $fixture, 'https://gravure-ch.com/search/new/photo', CrawlType::Listing);
        $metadata = array_column($this->metaOf($result->items, 'gallery'), 'metadata');

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($this->column($metadata, 'thumbnail_url')));
    }
}
