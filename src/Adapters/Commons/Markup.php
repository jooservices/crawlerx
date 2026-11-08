<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons;

/**
 * Turns the HTML fragments of Commons metadata (artist, credit, description) into plain data.
 */
final class Markup
{
    public static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = is_string($text) ? trim($text) : '';

        return $text === '' ? null : $text;
    }

    public static function firstLink(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/href="([^"]+)"/i', $value, $match) !== 1) {
            return null;
        }

        $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, '/')) {
            return Site::BASE_URL . $href;
        }

        return preg_match('~^https?://~i', $href) === 1 ? $href : null;
    }

    /** First valid calendar date (YYYY-MM-DD) found in the text. */
    public static function isoDate(mixed $value): ?string
    {
        $text = self::text($value);
        if ($text === null || preg_match('/(\d{4})-(\d{2})-(\d{2})/', $text, $match) !== 1) {
            return null;
        }

        return checkdate((int) $match[2], (int) $match[3], (int) $match[1]) ? $match[0] : null;
    }

    public static function isoTimestamp(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1 ? $value : null;
    }
}
