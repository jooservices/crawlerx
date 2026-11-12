<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The work's own gallery images in page order: each with its full-size original and its 1024px thumbnail.
 * The gallery block holds no related-product covers, so the page's other images are never read.
 */
final class ProductImages
{
    use NormalizesUrls;

    /** @return list<array{url: string, thumbnail_url: string, position: int}> */
    public function read(Crawler $dom): array
    {
        $images = [];
        foreach ($dom->filter('.product-gallery a.product-gallery__image') as $element) {
            $anchor = new Crawler($element);
            $full = $anchor->attr('data-full-size-src') ?? $anchor->attr('href');
            if ($full === null || trim($full) === '') {
                continue;
            }

            $url = $this->absolute(Site::BASE_URL, trim($full));
            if (in_array($url, array_column($images, 'url'), true)) {
                continue;
            }

            $thumbnail = $anchor->filter('noscript img[src]')->count() > 0
                ? (string) $anchor->filter('noscript img[src]')->first()->attr('src')
                : $full;
            $images[] = [
                'url' => $url,
                'thumbnail_url' => $this->absolute(Site::BASE_URL, trim($thumbnail)),
                'position' => count($images),
            ];
        }

        return $images;
    }
}
