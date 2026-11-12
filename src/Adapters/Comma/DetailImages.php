<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Comma;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Cover and gallery images of a COMMA VISION product. The gallery items are either a link to a full-size image around
 * a thumbnail, or a bare image; a link that does not point to an image file is not a full-size image.
 */
final class DetailImages
{
    use NormalizesUrls;
    use ParsesHtml;

    private const IMAGE_PATH = '#\.(?:jpe?g|png|gif|webp)$#i';

    public function cover(Crawler $root, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($root, Selectors::DETAIL_COVER, 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }

    /**
     * Cover first, then the gallery in page order; an image that appears twice is kept once.
     *
     * @return list<array{url: string, thumbnail_url: ?string}>
     */
    public function screenshots(Crawler $root, string $baseUrl, ?string $cover): array
    {
        $images = $cover === null ? [] : [['url' => $cover, 'thumbnail_url' => null]];

        return $this->unique([...$images, ...$this->gallery($root, $baseUrl)]);
    }

    /** @return list<array{url: string, thumbnail_url: ?string}> */
    private function gallery(Crawler $root, string $baseUrl): array
    {
        $images = [];
        $root->filterXPath(Selectors::GALLERY_ITEMS)->each(function (Crawler $item) use (&$images, $baseUrl): void {
            $link = $this->firstAttribute($item, 'a', 'href');
            $thumbnail = $this->firstAttribute($item, 'img', 'src');
            $full = $link !== null && preg_match(self::IMAGE_PATH, $link) === 1 ? $link : $thumbnail;
            if ($full === null) {
                return;
            }

            $images[] = [
                'url' => $this->absolute($baseUrl, $full),
                'thumbnail_url' => $full === $thumbnail || $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
            ];
        });

        return $images;
    }

    /**
     * @param  list<array{url: string, thumbnail_url: ?string}>  $images
     * @return list<array{url: string, thumbnail_url: ?string}>
     */
    private function unique(array $images): array
    {
        $byUrl = [];
        foreach ($images as $image) {
            $byUrl[$image['url']] ??= $image;
        }

        return array_values($byUrl);
    }
}
