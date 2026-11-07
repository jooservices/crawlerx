<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Indies;

use DOMElement;
use DOMNode;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/** Small parsing helpers shared by the Indies page types. */
final class Fields
{
    /** The last path segment after "/<segment>/" ("/title/dsdp049/" gives "dsdp049"). */
    public static function slug(string $url, string $segment): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('~/' . preg_quote($segment, '~') . '/([^/]+)/?$~', $path, $match) === 1
            ? rawurldecode($match[1])
            : null;
    }

    public static function clean(string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));

        return $text === '' ? null : $text;
    }

    /** The text of a cell without the hidden helper spans (display:none) the theme prints next to labels. */
    public static function visibleText(Crawler $cell): ?string
    {
        $text = '';
        foreach ($cell as $node) {
            $text .= self::collect($node);
        }

        return self::clean($text);
    }

    /** A profile value counts when it holds more than the empty template ("cm", "B:cm(カップ) / W: cm", "／"). */
    public static function meaningful(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $rest = preg_replace('/(?:cm|kg|カップ|[BWH]\s*[:：]|[\s\/／()（）:：-])+/iu', '', $value);

        return $rest === null || $rest === '' ? null : $value;
    }

    /**
     * "¥3,180 （税込 ¥3,498）" gives the list price and the tax-included price.
     *
     * @return array{price: array{amount: ?float, currency: ?string, label: ?string}|null, price_with_tax: array{amount: ?float, currency: ?string, label: ?string}|null}
     */
    public static function prices(?string $text): array
    {
        $amounts = [];
        if ($text !== null && preg_match_all('/[¥￥]\s*([\d,]+)/u', $text, $matches) > 0) {
            $amounts = array_map(static fn(string $amount): float => (float) str_replace(',', '', $amount), $matches[1]);
        }

        return [
            'price' => isset($amounts[0]) ? MovieMeta::price($amounts[0], 'JPY', $text) : null,
            'price_with_tax' => isset($amounts[1]) ? MovieMeta::price($amounts[1], 'JPY') : null,
        ];
    }

    /** The page number of ".../page/3/", or null for the first page. */
    public static function pageNumber(string $url): ?int
    {
        return preg_match('~/page/(\d+)/?(?:\?.*)?$~', $url, $match) === 1 ? (int) $match[1] : null;
    }

    private static function collect(DOMNode $node): string
    {
        if ($node instanceof DOMElement && preg_match('/display\s*:\s*none/i', $node->getAttribute('style')) === 1) {
            return '';
        }

        if ($node->childNodes->length === 0) {
            return $node->textContent;
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::collect($child);
        }

        return $text;
    }
}
