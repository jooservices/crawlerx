<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Wpb;

use JOOservices\CrawlerX\Adapters\Wpb\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Wpb\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_gallery_photos_cover_every_image_in_the_editorial_gallery(): void
    {
        $fixture = 'wpb/gallery-20261108-133432.html';
        $page = new Crawler($this->loadFixture($fixture));
        $expected = $page->filter('.group-entry-a .body-gravure-a picture img')->each(
            static fn(Crawler $image): string => UriResolver::resolve((string) $image->attr('src'), 'https://wpb.shueisha.co.jp/gravure/20261108-133432/'),
        );
        $gallery = $this->item(
            Gallery::class,
            $fixture,
            'https://wpb.shueisha.co.jp/gravure/20261108-133432/',
            CrawlType::Gallery,
        )->meta['gallery'];

        self::assertNotEmpty($expected);
        self::assertEqualsCanonicalizing($expected, $this->column($gallery['photos'], 'image_url'));
    }

    public function test_listing_keeps_each_card_cover_and_thumbnail(): void
    {
        $fixture = 'wpb/listing-page-1.html';
        $url = 'https://wpb.shueisha.co.jp/gravure/list/';
        $expected = [];
        (new Crawler($this->loadFixture($fixture)))->filter('ul.list-basic-a.c-grid--columns-2 > li article.item-gravure-a')->each(
            function (Crawler $card) use (&$expected, $url): void {
                $link = $card->filter('a.item-gravure-a__container[href]');
                $picture = $card->filter('picture.item-gravure-a__pict');
                if ($link->count() === 0 || $picture->count() === 0) {
                    return;
                }

                $cover = $this->fixtureCover($picture->first(), $url);
                $expected[UriResolver::resolve((string) $link->attr('href'), $url)] = $cover;
            },
        );
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

    /** @return array{url: string, thumbnail_url: ?string} */
    private function fixtureCover(Crawler $picture, string $baseUrl): array
    {
        $img = $picture->filter('img')->first();
        $url = UriResolver::resolve((string) $img->attr('src'), $baseUrl);
        $thumbnail = null;
        $picture->filter('source[srcset], img[srcset]')->each(static function (Crawler $node) use (&$thumbnail, $baseUrl): void {
            $srcset = $node->attr('srcset');
            if (! is_string($srcset) || $srcset === '') {
                return;
            }

            foreach (explode(',', $srcset) as $candidate) {
                $parts = preg_split('/\s+/', trim($candidate));
                $candidateUrl = $parts[0] ?? '';
                if ($candidateUrl !== '' && str_contains($candidateUrl, '/assets_c/')) {
                    $thumbnail = UriResolver::resolve($candidateUrl, $baseUrl);
                }
            }
        });

        return ['url' => $url, 'thumbnail_url' => $thumbnail];
    }
}
