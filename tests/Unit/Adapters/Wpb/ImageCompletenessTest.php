<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Wpb;

use JOOservices\CrawlerX\Adapters\Wpb\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Wpb\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_gallery_photos_cover_every_image_in_the_editorial_gallery(): void
    {
        $fixture = 'wpb/gallery-20261108-133432.html';
        $page = new Crawler($this->loadFixture($fixture));
        $expected = $page->filter('.group-entry-a .body-gravure-a picture img')->count();
        $gallery = $this->item(
            Gallery::class,
            $fixture,
            'https://wpb.shueisha.co.jp/gravure/20261108-133432/',
            CrawlType::Gallery,
        )->meta['gallery'];

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($gallery['photos']));
        self::assertGreaterThanOrEqual($expected, count($this->column($gallery['photos'], 'image_url')));
    }

    public function test_listing_keeps_each_card_cover_and_thumbnail(): void
    {
        $fixture = 'wpb/listing-page-1.html';
        $expected = (new Crawler($this->loadFixture($fixture)))
            ->filter('ul.list-basic-a.c-grid--columns-2 > li article.item-gravure-a picture img')
            ->count();
        $result = $this->list(Listing::class, $fixture, 'https://wpb.shueisha.co.jp/gravure/list/', CrawlType::Listing);
        $covers = array_column(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), 'cover');

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($this->column($covers, 'url')));
        self::assertGreaterThanOrEqual($expected, count($this->column($covers, 'thumbnail_url')));
    }
}
