<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Smartflash;

use JOOservices\CrawlerX\Adapters\Smartflash\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Smartflash\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    private const ARTICLE = 'https://smart-flash.jp/gravure/idol/432913/';

    public function test_gallery_keeps_each_article_and_viewer_photo_once(): void
    {
        $articleFixture = 'smartflash/gallery-432913.html';
        $viewerFixture = 'smartflash/gallery-432913-image-1.html';
        $article = new Crawler($this->loadFixture($articleFixture));
        $viewer = new Crawler($this->loadFixture($viewerFixture));
        $expected = array_values(array_unique([
            ...$this->imageUrls($article, '#main .newsBlock > .text img', self::ARTICLE),
            ...$this->imageUrls($viewer, '#main .imageSlider .item img', self::ARTICLE . 'image/1/'),
        ]));
        $client = $this->clientWithMappedResponses([
            self::ARTICLE => ['body' => $this->loadFixture($articleFixture)],
            self::ARTICLE . 'image/1/' => ['body' => $this->loadFixture($viewerFixture)],
        ]);
        $gallery = (new Gallery($client))->execute(new CrawlRequestDto(url: self::ARTICLE, type: CrawlType::Gallery))->meta['gallery'];

        $actual = $this->column($gallery['photos'], 'image_url');

        self::assertNotEmpty($expected);
        self::assertCount(count(array_unique($actual)), $actual);
        self::assertEqualsCanonicalizing($expected, $actual);
        self::assertNotEmpty($gallery['metadata']['cover']['url']);
    }

    public function test_listing_keeps_each_content_card_cover_pair(): void
    {
        $fixture = 'smartflash/listing-idol-page1.html';
        $page = new Crawler($this->loadFixture($fixture));
        $expected = [];
        $page->filter('#main ul.itemList > li')->each(function (Crawler $card) use (&$expected): void {
            $link = $card->filter('p.itemImage a[href]');
            $image = $card->filter('p.itemImage img');
            if ($link->count() === 0 || $image->count() === 0) {
                return;
            }

            $source = $image->first()->attr('data-src') ?? $image->first()->attr('src');
            if (is_string($source) && $source !== '') {
                $itemUrl = UriResolver::resolve((string) $link->attr('href'), self::ARTICLE);
                $expected[preg_replace('/\?.*$/', '', $itemUrl) ?? $itemUrl] = UriResolver::resolve($source, self::ARTICLE);
            }
        });
        $result = $this->list(Listing::class, $fixture, 'https://smart-flash.jp/gravure/idol/', CrawlType::Listing);
        $actual = [];
        foreach ($result->items as $item) {
            $cover = $item->meta['gallery']['metadata']['cover'] ?? [];
            $actual[$item->url] = [
                'url' => $cover['url'] ?? null,
                'thumbnail_url' => $cover['thumbnail_url'] ?? null,
            ];
        }

        self::assertNotEmpty($expected);
        self::assertSame(array_fill_keys(array_keys($expected), true), array_fill_keys(array_keys($actual), true));
        foreach ($expected as $url => $thumbnail) {
            self::assertSame($thumbnail, $actual[$url]['thumbnail_url'], $url);
            self::assertNotEmpty($actual[$url]['url'], $url);
        }
    }

    /** @return list<string> */
    private function imageUrls(Crawler $page, string $selector, string $baseUrl): array
    {
        return array_values(array_filter($page->filter($selector)->each(static function (Crawler $image) use ($baseUrl): ?string {
            foreach (['data-src', 'data-original', 'src'] as $attribute) {
                $url = $image->attr($attribute);
                if (is_string($url) && $url !== '' && ! str_starts_with($url, 'data:')) {
                    return UriResolver::resolve($url, $baseUrl);
                }
            }

            return null;
        })));
    }
}
