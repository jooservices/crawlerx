<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wpb\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\CoercesNumbers;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Wpb\Urls;
use Symfony\Component\DomCrawler\Crawler;

abstract class WpbHtmlType extends AbstractHtmlType
{
    use CoercesNumbers;
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'wpb';
    }

    /**
     * Highest-resolution URL from img/srcset/data-* plus a smaller thumbnail when both exist.
     *
     * @return array{url: string, thumbnail_url?: string, alt?: string, width?: int, height?: int}|null
     */
    protected function picture(Crawler $root, string $baseUrl): ?array
    {
        $img = $root->filter('img');
        if ($img->count() === 0) {
            return null;
        }
        $img = $img->first();

        /** @var list<array{url: string, width: ?int}> $candidates */
        $candidates = [];
        foreach (['data-src', 'data-original', 'src'] as $attribute) {
            $value = $img->attr($attribute);
            if (is_string($value) && trim($value) !== '' && ! str_starts_with(trim($value), 'data:')) {
                $candidates[] = ['url' => $this->absolute($baseUrl, trim($value)), 'width' => null];
            }
        }

        $picture = $img->closest('picture');
        $srcsetNodes = $picture !== null && $picture->count() > 0
            ? $picture->filter('source[srcset], img[srcset]')
            : $img;
        $srcsetNodes->each(function (Crawler $node) use (&$candidates, $baseUrl): void {
            $srcset = $node->attr('srcset');
            if (! is_string($srcset) || trim($srcset) === '') {
                return;
            }
            foreach ($this->parseSrcset($srcset, $baseUrl) as $entry) {
                $candidates[] = $entry;
            }
        });

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static fn(array $left, array $right): int => ($right['width'] ?? 0) <=> ($left['width'] ?? 0),
        );

        $best = $candidates[0];
        $full = Urls::preferOriginal($best['url']);
        $thumb = null;
        foreach (array_reverse($candidates) as $entry) {
            $url = $entry['url'];
            if ($url !== $full && (str_contains($url, '-thumb-') || str_contains($url, '/assets_c/'))) {
                $thumb = $url;
                break;
            }
        }

        $result = ['url' => $full];
        if ($thumb !== null) {
            $result['thumbnail_url'] = $thumb;
        }
        $alt = $this->normalizeText((string) $img->attr('alt'));
        if ($alt !== null) {
            $result['alt'] = $alt;
        }
        $width = $this->positiveInt($img->attr('width')) ?? $best['width'];
        if ($width !== null) {
            $result['width'] = $width;
        }
        $height = $this->positiveInt($img->attr('height'));
        if ($height !== null) {
            $result['height'] = $height;
        }

        return $result;
    }

    /**
     * @return list<array{url: string, width: ?int}>
     */
    protected function parseSrcset(string $srcset, string $baseUrl): array
    {
        $entries = [];
        foreach (explode(',', $srcset) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $bits = preg_split('/\s+/', $part);
            if ($bits === false) {
                $bits = [];
            }
            $url = $bits[0] ?? '';
            if ($url === '' || str_starts_with($url, 'data:')) {
                continue;
            }
            $width = null;
            if (isset($bits[1]) && preg_match('/^(\d+)w$/i', $bits[1], $match) === 1) {
                $width = (int) $match[1];
            }
            $entries[] = ['url' => $this->absolute($baseUrl, $url), 'width' => $width];
        }

        return $entries;
    }
}
