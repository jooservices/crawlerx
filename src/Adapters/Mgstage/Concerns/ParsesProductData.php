<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Mgstage\Concerns;

use JOOservices\CrawlerX\Adapters\Mgstage\Site;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Value parsers shared by the MGStage listing cards and the detail page.
 */
trait ParsesProductData
{
    protected function skuFromUrl(string $url): ?string
    {
        return $this->pathMatch($url, '#/product/product_detail/([^/?\#]+)#');
    }

    /**
     * "4.5 (4件)" or "3.7 (4 件)" to a rating out of five.
     *
     * @return array{score: float, scale: ?float, votes: ?int}|null
     */
    protected function ratingFromText(?string $text): ?array
    {
        if ($text === null || preg_match('/(\d+(?:\.\d+)?)\s*\(\s*(\d+)\s*件/u', $text, $match) !== 1 || (int) $match[2] === 0) {
            return null;
        }

        return MovieMeta::rating((float) $match[1], Site::RATING_SCALE, (int) $match[2]);
    }

    /** Percent-encodes the raw spaces and Japanese characters the site leaves in some hrefs. */
    protected function encodedUrl(string $url): string
    {
        return (string) preg_replace_callback('/[^\x21-\x7e]+/u', static fn(array $m): string => rawurlencode($m[0]), $url);
    }

    /** The first number of a counter text such as "欲しいものリスト追加数：348" or "39,358". */
    protected function countFromText(?string $text): ?int
    {
        if ($text === null || preg_match('/\d[\d,]*/', $text, $match) !== 1) {
            return null;
        }

        return (int) str_replace(',', '', $match[0]);
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    protected function yenFromText(?string $text): ?array
    {
        if ($text === null || preg_match('/\d[\d,]*/', $text, $match) !== 1) {
            return null;
        }

        return MovieMeta::price((float) str_replace(',', '', $match[0]), Site::CURRENCY, $text);
    }

    /** "2026/11/10 10:00" or "2026-11-10T10:00:00" (Japan time) to an ISO 8601 timestamp with offset. */
    protected function saleEnd(?string $text): ?string
    {
        if ($text === null || preg_match('/(\d{4})[\/-](\d{2})[\/-](\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/', $text, $m) !== 1) {
            return null;
        }

        return sprintf('%s-%s-%sT%s:%s:%s%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6] ?? '00', Site::UTC_OFFSET);
    }

    /**
     * Badge names from the `btn_<name>` / `btn_<name>_big` marker spans ("dokusen" = exclusive to the store).
     *
     * @return list<string>
     */
    protected function badges(Crawler $nodes): array
    {
        $badges = [];
        $nodes->each(function (Crawler $node) use (&$badges): void {
            foreach (explode(' ', (string) $node->attr('class')) as $class) {
                if (preg_match('/^btn_([a-z0-9]+?)(?:_big)?$/', $class, $match) === 1 && $match[1] !== 'position') {
                    $badges[] = $match[1];
                }
            }
        });

        return array_values(array_unique($badges));
    }
}
