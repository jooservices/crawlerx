<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Smartflash;

use JOOservices\CrawlerX\Adapters\Smartflash\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Smartflash\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const ARTICLE = 'https://smart-flash.jp/gravure/idol/432913/';

    public function test_the_gallery_keeps_the_article_photo_plus_every_viewer_photo_and_the_cover(): void
    {
        $client = $this->clientWithMappedResponses([
            self::ARTICLE => ['body' => $this->loadFixture('smartflash/gallery-432913.html')],
            self::ARTICLE . 'image/1/' => ['body' => $this->loadFixture('smartflash/gallery-432913-image-1.html')],
        ]);
        $gallery = (new Gallery($client))->execute(new CrawlRequestDto(url: self::ARTICLE, type: CrawlType::Gallery))->meta['gallery'];

        self::assertCount(10, $this->column($gallery['photos'], 'image_url'));
        self::assertCount(10, array_unique($this->column($gallery['photos'], 'image_url')));
        self::assertSame('https://data.smart-flash.jp/wp-content/uploads/2026/10/10130450/omura_anzu1_1.jpg', $gallery['photos'][0]['image_url']);
        self::assertSame('https://data.smart-flash.jp/wp-content/uploads/2026/10/10130453/omura_anzu1_thumb.jpg', $gallery['metadata']['cover']['url']);
        self::assertSame('https://data.smart-flash.jp/wp-content/uploads/2025/01/20141448/smartflash.jpg', $gallery['metadata']['author_image_url']);
        self::assertStringNotContainsString('-228x300', implode(' ', $this->column($gallery['photos'], 'image_url')), 'the ranking sidebar thumbnails are not photos');
    }

    public function test_every_listing_card_keeps_the_cover_and_its_thumbnail(): void
    {
        $result = $this->list(Listing::class, 'smartflash/listing-idol-page1.html', 'https://smart-flash.jp/gravure/idol/', CrawlType::Listing);
        $covers = array_column(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), 'cover');

        self::assertCount(30, $this->column($covers, 'url'));
        self::assertCount(30, $this->column($covers, 'thumbnail_url'));
    }
}
