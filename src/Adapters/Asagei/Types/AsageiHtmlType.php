<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Asagei\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Asagei\Urls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

abstract class AsageiHtmlType extends AbstractHtmlType
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'asagei';
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    protected function labelledLinks(Crawler $root, string $label, string $baseUrl): array
    {
        $links = [];
        $root->filter('.content-cats-tag-head')->each(function (Crawler $head) use (&$links, $label, $baseUrl): void {
            $text = $this->normalizeText($head->text('')) ?? '';
            if (! str_contains($text, $label)) {
                return;
            }

            $list = $head->nextAll()->filter('ul.content-cats')->first();
            if ($list->count() === 0) {
                return;
            }

            $list->filter('a')->each(function (Crawler $anchor) use (&$links, $baseUrl): void {
                $name = $this->normalizeText($anchor->text(''));
                $href = $anchor->attr('href');
                if ($name === null || ! is_string($href) || trim($href) === '') {
                    return;
                }

                $url = $this->absolute($baseUrl, trim($href));
                $links[$url] = ['name' => $name, 'url' => $url];
            });
        });

        return array_values($links);
    }

    /**
     * @param  list<array{name: string, url: string}>  $links
     * @return list<string>
     */
    protected function linkNames(array $links): array
    {
        $names = [];
        foreach ($links as $link) {
            $names[] = $link['name'];
        }

        return $names;
    }

    /**
     * @param  list<array{name: string, url: string}>  $links
     * @return list<string>
     */
    protected function linkUrls(array $links): array
    {
        $urls = [];
        foreach ($links as $link) {
            $urls[] = $link['url'];
        }

        return $urls;
    }

    /** @return array{date: ?string, published_at: ?string, raw: ?string} */
    protected function postedAt(Crawler $crawler, string $selector, string $datetimeSelector): array
    {
        $raw = $this->firstText($crawler, $selector);
        $datetime = $this->firstAttribute($crawler, $datetimeSelector, 'datetime');
        $source = $datetime ?? $raw;
        $publishedAt = $this->isoDateTime($source);
        $date = MovieMeta::isoDate($source) ?? ($publishedAt === null ? null : substr($publishedAt, 0, 10));

        return [
            'date' => $date,
            'published_at' => $publishedAt,
            'raw' => $raw === null ? null : preg_replace('/^Posted on\s+/u', '', $raw),
        ];
    }

    /** "2026-09-17 16:00" or "2026年09月17日 16:00" → ISO 8601 with +09:00. */
    protected function isoDateTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/(\d{4})[-\/.年]\s*(\d{1,2})[-\/.月]\s*(\d{1,2})日?(?:\s+|T)(\d{1,2}):(\d{2})(?::(\d{2}))?/u', $value, $match) !== 1) {
            return null;
        }

        $second = $match[6] ?? '00';

        return sprintf(
            '%04d-%02d-%02dT%02d:%02d:%02d+09:00',
            (int) $match[1],
            (int) $match[2],
            (int) $match[3],
            (int) $match[4],
            (int) $match[5],
            (int) $second,
        );
    }

    protected function imageSource(Crawler $img): ?string
    {
        foreach (['data-src', 'data-original', 'data-lazy-src', 'src'] as $attribute) {
            $value = $img->attr($attribute);
            if (is_string($value) && trim($value) !== '' && ! str_starts_with(trim($value), 'data:')) {
                return trim($value);
            }
        }

        $srcset = $img->attr('srcset');
        if (! is_string($srcset) || trim($srcset) === '') {
            return null;
        }

        $candidates = array_map(static fn(string $part): string => trim(explode(' ', trim($part))[0]), explode(',', $srcset));
        $candidates = array_values(array_filter($candidates, static fn(string $url): bool => $url !== ''));

        return $candidates === [] ? null : $candidates[array_key_last($candidates)];
    }

    protected function isSiteChromeImage(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return true;
        }

        return str_contains($path, '/asagei/assets/')
            || str_contains($path, '/favicon')
            || str_contains($path, 'apple-touch-icon')
            || ! str_contains($path, '/asagei/uploads/');
    }

    /** @return array{url: string, thumbnail_url: ?string, mime_type: ?string}|null */
    protected function coverFromUrl(?string $src, string $baseUrl): ?array
    {
        if ($src === null || $src === '') {
            return null;
        }

        $thumbnail = $this->absolute($baseUrl, $src);
        if ($this->isSiteChromeImage($thumbnail)) {
            return null;
        }

        $original = Urls::original($thumbnail);

        return [
            'url' => $original,
            'thumbnail_url' => $original === $thumbnail ? null : $thumbnail,
            'mime_type' => Urls::mimeType($original),
        ];
    }
}
