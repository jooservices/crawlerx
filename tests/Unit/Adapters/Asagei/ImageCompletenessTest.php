<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Asagei;

use JOOservices\CrawlerX\Adapters\Asagei\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Asagei\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_gallery_keeps_every_image_inside_the_article_body(): void
    {
        $fixture = 'asagei/gallery-349499.html';
        $expected = (new Crawler($this->loadFixture($fixture)))->filter('#entry-content img')->count();
        $gallery = $this->item(
            Gallery::class,
            $fixture,
            'https://www.asagei.com/excerpt/349499',
            CrawlType::Gallery,
        )->meta['gallery'];

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($gallery['photos']));
        self::assertGreaterThanOrEqual($expected, count($this->column($gallery['photos'], 'image_url')));
    }

    public function test_listing_keeps_a_cover_pair_for_each_content_card(): void
    {
        $fixture = 'asagei/listing-gravure-page1.html';
        $page = new Crawler($this->loadFixture($fixture));
        $expected = $page->filter('main.main-area .content-area > .content-list')->reduce(
            static fn(Crawler $card): bool => $card->filter('.content-thumb-pc img, .content-thumb-sp img')->count() > 0,
        )->count();
        $result = $this->list(Listing::class, $fixture, 'https://www.asagei.com/tag/%E3%82%B0%E3%83%A9%E3%83%93%E3%82%A2', CrawlType::Listing);
        $covers = array_column(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), 'cover');

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($this->column($covers, 'url')));
        self::assertGreaterThanOrEqual($expected, count($this->column($covers, 'thumbnail_url')));
    }
}
