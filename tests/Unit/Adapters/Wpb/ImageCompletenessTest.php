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
        $url = 'https://wpb.shueisha.co.jp/gravure/20261108-133432/';
        $page = new Crawler($this->loadFixture($fixture));
        $expectedByUrl = [];
        $page->filter('.group-entry-a .body-gravure-a img[src]')->each(function (Crawler $image) use (&$expectedByUrl, $url): void {
            $imageUrl = UriResolver::resolve((string) $image->attr('src'), $url);
            $picture = $image->ancestors()->filter('picture')->first();
            $expectedByUrl[$imageUrl] = [
                'url' => $imageUrl,
                'thumbnail_url' => $picture->count() === 0 ? null : $this->fixtureThumbnail($picture, $url),
            ];
        });
        $ogImage = $page->filter('meta[property="og:image"][content]')->first()->attr('content');
        if (is_string($ogImage) && $ogImage !== '') {
            $ogImage = UriResolver::resolve($ogImage, $url);
            if (! $this->isChromeImage($ogImage) && ! isset($expectedByUrl[$ogImage])) {
                $expectedByUrl[$ogImage] = ['url' => $ogImage, 'thumbnail_url' => null];
            }
        }

        $expected = array_values($expectedByUrl);
        $gallery = $this->item(
            Gallery::class,
            $fixture,
            $url,
            CrawlType::Gallery,
        )->meta['gallery'];
        $photos = array_map(
            static fn(array $photo): array => [
                'url' => $photo['image_url'] ?? null,
                'thumbnail_url' => $photo['thumbnail_url'] ?? null,
            ],
            $gallery['photos'],
        );
        $cover = $gallery['metadata']['cover'];

        self::assertNotEmpty($expected);
        self::assertCount(count($expected), $photos);
        self::assertSame($expected, $photos);
        $expectedCover = $expected[0];
        self::assertSame(
            ['url' => $expectedCover['url'], 'thumbnail_url' => $expectedCover['thumbnail_url']],
            array_intersect_key($cover, ['url' => true, 'thumbnail_url' => true]),
        );
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

    private function fixtureThumbnail(Crawler $picture, string $baseUrl): ?string
    {
        $thumbnail = null;
        $thumbnailWidth = null;
        $picture->filter('source[srcset], img[srcset]')->each(static function (Crawler $node) use (&$thumbnail, &$thumbnailWidth, $baseUrl): void {
            $srcset = $node->attr('srcset');
            if (! is_string($srcset) || $srcset === '') {
                return;
            }

            foreach (explode(',', $srcset) as $candidate) {
                $parts = preg_split('/\s+/', trim($candidate));
                $candidateUrl = $parts[0] ?? '';
                if ($candidateUrl === '' || (! str_contains($candidateUrl, '-thumb-') && ! str_contains($candidateUrl, '/assets_c/'))) {
                    continue;
                }

                $width = isset($parts[1]) && preg_match('/^(\d+)w$/', $parts[1], $match) === 1 ? (int) $match[1] : null;
                if ($thumbnail === null || ($width !== null && ($thumbnailWidth === null || $width < $thumbnailWidth))) {
                    $thumbnail = UriResolver::resolve($candidateUrl, $baseUrl);
                    $thumbnailWidth = $width;
                }
            }
        });

        return $thumbnail;
    }

    private function isChromeImage(string $url): bool
    {
        return str_contains($url, '/images/common/')
            || str_contains($url, '/images/bnr/')
            || str_contains($url, '/data/banner/')
            || str_contains($url, '4375d21660a17e6b0e50d5402d16cb885ab42bd1');
    }
}
