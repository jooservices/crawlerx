<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Asagei;

use JOOservices\CrawlerX\Adapters\Asagei\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Asagei\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_gallery_keeps_every_image_inside_the_article_body(): void
    {
        $fixture = 'asagei/gallery-349499.html';
        $page = new Crawler($this->loadFixture($fixture));
        $expected = [];
        $page->filter('#entry-content img')->each(static function (Crawler $image) use (&$expected): void {
            $source = $image->attr('data-src') ?? $image->attr('data-original') ?? $image->attr('data-lazy-src') ?? $image->attr('src');
            if (! is_string($source) || $source === '' || str_starts_with($source, 'data:')) {
                return;
            }

            $full = preg_replace('#-\d+x\d+(\.[a-z0-9]{3,4})$#i', '$1', $source);
            $expected[] = [
                'image_url' => is_string($full) ? $full : $source,
                'thumbnail_url' => $full === $source ? null : $source,
            ];
        });
        $expected = array_values(array_unique($expected, SORT_REGULAR));
        $gallery = $this->item(
            Gallery::class,
            $fixture,
            'https://www.asagei.com/excerpt/349499',
            CrawlType::Gallery,
        )->meta['gallery'];

        self::assertNotEmpty($expected);
        self::assertEqualsCanonicalizing($expected, array_map(
            static fn(array $photo): array => [
                'image_url' => $photo['image_url'],
                'thumbnail_url' => $photo['thumbnail_url'] ?? null,
            ],
            $gallery['photos'],
        ));
    }

    public function test_listing_keeps_a_cover_pair_for_each_content_card(): void
    {
        $fixture = 'asagei/listing-gravure-page1.html';
        $page = new Crawler($this->loadFixture($fixture));
        $url = 'https://www.asagei.com/tag/%E3%82%B0%E3%83%A9%E3%83%93%E3%82%A2';
        $expected = [];
        $page->filter('main.main-area .content-area > .content-list')->each(static function (Crawler $card) use (&$expected, $url): void {
            $link = $card->filter('h2.content-title a[href]');
            $image = $card->filter('.content-thumb-pc img, .content-thumb-sp img');
            if ($link->count() === 0 || $image->count() === 0) {
                return;
            }

            $source = $image->first()->attr('data-src') ?? $image->first()->attr('data-original') ?? $image->first()->attr('data-lazy-src') ?? $image->first()->attr('src');
            if (! is_string($source) || $source === '' || str_starts_with($source, 'data:')) {
                return;
            }
            $thumbnail = UriResolver::resolve($source, $url);
            $full = preg_replace('#-\d+x\d+(\.[a-z0-9]{3,4})$#i', '$1', $thumbnail);
            $expected[UriResolver::resolve((string) $link->attr('href'), $url)] = [
                'url' => is_string($full) ? $full : $thumbnail,
                'thumbnail_url' => $full === $thumbnail ? null : $thumbnail,
            ];
        });
        $result = $this->list(Listing::class, $fixture, $url, CrawlType::Listing);
        $actual = [];
        foreach ($result->items as $item) {
            $cover = $item->meta['gallery']['metadata']['cover'] ?? [];
            $actual[$item->url] = [
                'url' => $cover['url'] ?? null,
                'thumbnail_url' => $cover['thumbnail_url'] ?? null,
            ];
        }

        self::assertNotEmpty($expected);
        self::assertSame($expected, $actual);
    }
}
