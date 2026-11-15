<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The work's own images: the WooCommerce product gallery, in page order, each as its full-size link and its
 * lazy-load thumbnail. Related-product covers and site chrome are outside the gallery and never read.
 */
final class GalleryImages
{
    use NormalizesUrls;
    use ParsesHtml;

    /** @return list<array{url: string, thumbnail_url: ?string}> */
    public function read(Crawler $page): array
    {
        $images = [];
        $page->filter(Selectors::GALLERY_ITEM)->each(function (Crawler $item) use (&$images): void {
            $full = $this->firstAttribute($item, 'a[href]', 'href');
            if ($full === null) {
                return;
            }

            $url = $this->absolute(Site::BASE_URL, $full);
            $thumbnail = $this->firstAttribute($item, 'img', 'data-src');
            $images[$url] ??= [
                'url' => $url,
                'thumbnail_url' => $thumbnail === null ? null : $this->absolute(Site::BASE_URL, $thumbnail),
            ];
        });

        return array_values($images);
    }
}
