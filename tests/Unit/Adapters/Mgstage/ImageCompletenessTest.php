<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Mgstage;

use JOOservices\CrawlerX\Adapters\Mgstage\Types\Detail;
use JOOservices\CrawlerX\Adapters\Mgstage\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_each_detail_keeps_its_cover_and_every_sample_still_pair(): void
    {
        foreach ([
            ['detail-259luxu-1000.html', 'https://www.mgstage.com/product/product_detail/259LUXU-1000/'],
            ['detail-siro-5767.html', 'https://www.mgstage.com/product/product_detail/SIRO-5767/'],
        ] as [$fixtureName, $url]) {
            $fixture = 'mgstage/' . $fixtureName;
            $expected = $this->detailImages($fixture, $url);
            $movie = $this->item(Detail::class, $fixture, $url, CrawlType::Detail)->meta['movie'];

            self::assertNotEmpty($expected['screenshots'], $fixtureName);
            self::assertSame($expected['cover_url'], $movie['cover_url'], $fixtureName . ' large jacket');
            self::assertSame($expected['cover_thumbnail_url'], $movie['metadata']['cover_thumbnail_url'], $fixtureName . ' jacket thumbnail');
            self::assertSame($expected['screenshots'], array_map(static fn(array $screenshot): array => [
                'url' => $screenshot['url'],
                'thumbnail_url' => $screenshot['thumbnail_url'] ?? null,
            ], $movie['screenshots']), $fixtureName . ' sample stills');
        }
    }

    public function test_listing_keeps_every_card_cover_and_sample_still(): void
    {
        $fixture = 'mgstage/listing-new.html';
        $url = 'https://www.mgstage.com/search/cSearch.php?search_word=&sort=new&list_cnt=30&type=top';
        $expected = $this->listingImages($fixture, $url);
        $result = $this->list(
            Listing::class,
            $fixture,
            $url,
            CrawlType::Listing,
        );
        $actual = [];
        foreach ($result->items as $item) {
            $movie = $item->meta['movie'];
            $actual[$item->url] = [
                'cover_url' => $movie['cover_url'] ?? null,
                'cover_thumbnail_url' => $movie['metadata']['cover_thumbnail_url'] ?? null,
                'screenshots' => array_column($movie['screenshots'], 'url'),
            ];
        }

        self::assertNotEmpty($expected);
        self::assertSame($expected, $actual);
    }

    /** @return array{cover_url: string, cover_thumbnail_url: string, screenshots: list<array{url: string, thumbnail_url: string}>} */
    private function detailImages(string $fixture, string $baseUrl): array
    {
        $page = new Crawler($this->loadFixture($fixture));
        $coverUrl = (string) $page->filter('a#EnlargeImage[href]')->first()->attr('href');
        $thumbnailUrl = (string) $page->filter('img.enlarge_image[src]')->first()->attr('src');
        $screenshots = [];
        $page->filter('dl#sample-photo a.sample_image[href]')->each(static function (Crawler $link) use (&$screenshots, $baseUrl): void {
            $image = $link->filter('img[src]');
            if ($image->count() === 0) {
                return;
            }

            $screenshots[] = [
                'url' => UriResolver::resolve((string) $link->attr('href'), $baseUrl),
                'thumbnail_url' => UriResolver::resolve((string) $image->first()->attr('src'), $baseUrl),
            ];
        });

        return [
            'cover_url' => UriResolver::resolve($coverUrl, $baseUrl),
            'cover_thumbnail_url' => UriResolver::resolve($thumbnailUrl, $baseUrl),
            'screenshots' => $screenshots,
        ];
    }

    /** @return array<string, array{cover_url: string, cover_thumbnail_url: string, screenshots: list<string>}> */
    private function listingImages(string $fixture, string $baseUrl): array
    {
        $images = [];
        $page = new Crawler($this->loadFixture($fixture));
        $page->filter('li.product_list_item')->each(function (Crawler $card) use (&$images, $baseUrl): void {
            $link = $card->filter('a[href*="/product/product_detail/"]');
            $thumbnail = $card->filter('h5 img[src]');
            if ($link->count() === 0 || $thumbnail->count() === 0) {
                return;
            }

            $itemUrl = UriResolver::resolve((string) $link->first()->attr('href'), $baseUrl);
            $thumbnailUrl = UriResolver::resolve((string) $thumbnail->first()->attr('src'), $baseUrl);
            $largeCover = $card->filter('.sample_image_btn a[href*="pb_e_"]');
            $screenshots = $card->filter('.sample_image_btn a[href*="cap_e_"]')->each(
                static fn(Crawler $sample): string => UriResolver::resolve((string) $sample->attr('href'), $baseUrl),
            );
            $images[$itemUrl] = [
                'cover_url' => $largeCover->count() > 0
                    ? UriResolver::resolve((string) $largeCover->first()->attr('href'), $baseUrl)
                    : $thumbnailUrl,
                'cover_thumbnail_url' => $thumbnailUrl,
                'screenshots' => $screenshots,
            ];
        });

        return $images;
    }
}
