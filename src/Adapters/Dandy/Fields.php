<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dandy;

use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Value parsers shared by the listing and detail types of the DANDY GROUP site.
 */
final class Fields
{
    public static function text(Crawler $node, string $selector): ?string
    {
        $match = $node->filter($selector);

        return $match->count() > 0 ? self::clean($match->first()->text('')) : null;
    }

    public static function clean(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text ?? ''));

        return $text === '' ? null : $text;
    }

    /**
     * Release dates are printed `2026/11/29`, optionally behind a label (`動画配信日：2026/11/29`).
     */
    public static function isoDate(?string $text): ?string
    {
        return MovieMeta::isoDate($text);
    }

    /**
     * The full-size image of a lazy-loaded `<img>` and its smallest variant, read from data-src / data-srcset (the
     * `src` of a lazy image is a base64 placeholder). The full URL is the widest srcset entry.
     *
     * @return array{url: string, thumbnail_url: ?string, width: ?int}|null
     */
    public static function image(Crawler $img): ?array
    {
        $candidates = self::srcset($img->attr('data-srcset') ?? $img->attr('srcset') ?? '');
        foreach (['data-src', 'data-original', 'src'] as $attribute) {
            $value = trim((string) $img->attr($attribute));
            if ($value !== '' && ! str_starts_with($value, 'data:')) {
                $width = (int) $img->attr('data-eio-rwidth');
                $candidates[] = ['url' => Site::absolute($value), 'width' => $width > 0 ? $width : 0];
                break;
            }
        }
        if ($candidates === []) {
            return null;
        }

        $byUrl = [];
        foreach ($candidates as $candidate) {
            $byUrl[$candidate['url']] = max($byUrl[$candidate['url']] ?? 0, $candidate['width']);
        }
        arsort($byUrl);
        $candidates = [];
        foreach ($byUrl as $url => $width) {
            $candidates[] = ['url' => $url, 'width' => $width];
        }
        $full = $candidates[0];
        $small = $candidates[count($candidates) - 1];

        return [
            'url' => $full['url'],
            'thumbnail_url' => $small['url'] !== $full['url'] ? $small['url'] : null,
            'width' => $full['width'] > 0 ? $full['width'] : null,
        ];
    }

    /**
     * Every distinct image under the selector, in document order (the `<noscript>` copies repeat the same URL).
     *
     * @return list<array{url: string, thumbnail_url: ?string, width: ?int}>
     */
    public static function images(Crawler $root, string $selector): array
    {
        $images = [];
        foreach ($root->filter($selector) as $element) {
            $image = self::image(new Crawler($element));
            if ($image !== null && ! isset($images[$image['url']])) {
                $images[$image['url']] = $image;
            }
        }

        return array_values($images);
    }

    /** @return list<array{url: string, width: int}> */
    private static function srcset(string $srcset): array
    {
        $entries = [];
        foreach (explode(',', $srcset) as $entry) {
            $parts = preg_split('/\s+/', trim($entry));
            if ($parts === false || ($parts[0] ?? '') === '') {
                continue;
            }
            $entries[] = ['url' => Site::absolute($parts[0]), 'width' => (int) ($parts[1] ?? 0)];
        }

        return $entries;
    }
}
