<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Smartflash;

use JOOservices\CrawlerX\Adapters\Smartflash\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Smartflash\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    private const ARTICLE = 'https://smart-flash.jp/gravure/idol/432913/';

    public function test_gallery_keeps_each_article_and_viewer_photo_once(): void
    {
        $articleFixture = 'smartflash/gallery-432913.html';
        $viewerFixture = 'smartflash/gallery-432913-image-1.html';
        $article = new Crawler($this->loadFixture($articleFixture));
        $viewer = new Crawler($this->loadFixture($viewerFixture));
        $expected = count(array_unique([
            ...$this->imageUrls($article, '#main .newsBlock > .text img'),
            ...$this->imageUrls($viewer, '#main .imageSlider .item img'),
        ]));
        $client = $this->clientWithMappedResponses([
            self::ARTICLE => ['body' => $this->loadFixture($articleFixture)],
            self::ARTICLE . 'image/1/' => ['body' => $this->loadFixture($viewerFixture)],
        ]);
        $gallery = (new Gallery($client))->execute(new CrawlRequestDto(url: self::ARTICLE, type: CrawlType::Gallery))->meta['gallery'];

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($gallery['photos']));
        self::assertGreaterThanOrEqual($expected, count($this->column($gallery['photos'], 'image_url')));
        self::assertNotEmpty($gallery['metadata']['cover']['url']);
    }

    public function test_listing_keeps_each_content_card_cover_pair(): void
    {
        $fixture = 'smartflash/listing-idol-page1.html';
        $page = new Crawler($this->loadFixture($fixture));
        $expected = $page->filter('#main ul.itemList > li')->reduce(
            static fn(Crawler $card): bool => $card->filter('p.itemImage img')->count() > 0,
        )->count();
        $result = $this->list(Listing::class, $fixture, 'https://smart-flash.jp/gravure/idol/', CrawlType::Listing);
        $covers = array_column(array_column($this->metaOf($result->items, 'gallery'), 'metadata'), 'cover');

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($this->column($covers, 'url')));
        self::assertGreaterThanOrEqual($expected, count($this->column($covers, 'thumbnail_url')));
    }

    /** @return list<string> */
    private function imageUrls(Crawler $page, string $selector): array
    {
        return array_values(array_filter($page->filter($selector)->each(static function (Crawler $image): ?string {
            foreach (['data-src', 'data-original', 'src'] as $attribute) {
                $url = $image->attr($attribute);
                if (is_string($url) && $url !== '' && ! str_starts_with($url, 'data:')) {
                    return $url;
                }
            }

            return null;
        })));
    }
}
