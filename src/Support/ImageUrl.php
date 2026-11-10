<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

/**
 * Image URL clean-up shared by the adapters that list images served through a re-compressing or resizing CDN.
 */
final class ImageUrl
{
    /** Query parameters a CDN uses to re-compress or resize an upload; without them it serves the original. */
    private const RECOMPRESSION_PARAMS = ['output-quality', 'resize'];

    /** FC2's thumbnail proxy: `contents-thumbnail2.fc2.com/w360/<storage host>/<path>` serves a resized copy of `https://<storage host>/<path>`. */
    private const FC2_THUMBNAIL = '#^(?:https?:)?//contents-thumbnail\d*\.fc2\.com/w\d+(?:h\d+)?/(storage\d+\.contents\.fc2\.com/.+)$#';

    /** The URL without its re-compression parameters (other query parameters stay, in their order). */
    public static function original(string $url): string
    {
        if (preg_match(self::FC2_THUMBNAIL, $url, $match) === 1) {
            return 'https://' . $match[1];
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return $url;
        }

        $kept = array_filter(
            explode('&', $query),
            static fn(string $pair): bool => ! in_array(urldecode(explode('=', $pair, 2)[0]), self::RECOMPRESSION_PARAMS, true),
        );
        $base = explode('?', $url, 2)[0];

        return $kept === [] ? $base : $base . '?' . implode('&', $kept);
    }
}
