<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Gravurech;

use JOOservices\CrawlerX\Adapters\Gravurech\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Gravurech\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_each_public_detail_keeps_its_cover_without_related_work_images(): void
    {
        foreach ([
            ['detail-j6R.html', 'j6R'],
            ['detail-0bb.html', '0bb'],
        ] as [$fixtureName, $id]) {
            $fixture = 'gravurech/' . $fixtureName;
            $image = (new Crawler($this->loadFixture($fixture)))->filter('.product-detail .thumbnail img[src]')->first();
            $expected = UriResolver::resolve((string) $image->attr('src'), 'https://gravure-ch.com/content/' . $id);
            $gallery = $this->item(
                Gallery::class,
                $fixture,
                'https://gravure-ch.com/content/' . $id,
                CrawlType::Gallery,
            )->meta['gallery'];

            self::assertNotSame('', $expected, $fixtureName);
            self::assertSame([$expected], $this->column($gallery['photos'], 'image_url'), $fixtureName);
        }
    }

    public function test_listing_keeps_each_product_thumbnail(): void
    {
        $fixture = 'gravurech/listing-new-photo.html';
        $url = 'https://gravure-ch.com/search/new/photo';
        $expected = [];
        (new Crawler($this->loadFixture($fixture)))->filter('.products__list > .product')->each(static function (Crawler $card) use (&$expected, $url): void {
            $link = $card->filter('.thumbnail a[href]');
            $image = $card->filter('.thumbnail img[src]');
            if ($link->count() > 0 && $image->count() > 0) {
                $expected[UriResolver::resolve((string) $link->attr('href'), $url)] = UriResolver::resolve((string) $image->first()->attr('src'), $url);
            }
        });
        $result = $this->list(Listing::class, $fixture, $url, CrawlType::Listing);
        $actual = [];
        foreach ($result->items as $item) {
            $actual[$item->url] = $item->meta['gallery']['metadata']['thumbnail_url'] ?? null;
        }

        self::assertNotEmpty($expected);
        self::assertSame($expected, $actual);
    }
}
