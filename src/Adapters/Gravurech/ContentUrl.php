<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravurech;

final class ContentUrl
{
    /** The site's own short code of a work: /content/j6R -> j6R. */
    public static function code(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/content/([A-Za-z0-9]+)/?$#', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /** The numeric database id that prefixes the cover file name: /storage/newPhoto/1713_thumbnail1.jpg -> 1713. */
    public static function numericId(?string $imageUrl): ?string
    {
        if ($imageUrl !== null && preg_match('#/(\d+)_thumbnail\d*\.[a-z]+$#i', $imageUrl, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /** The page number of a listing URL: ?page=3 -> 3; no parameter means the first page. */
    public static function page(string $url): int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $page = $query['page'] ?? null;

        return is_string($page) && ctype_digit($page) ? max(1, (int) $page) : 1;
    }
}
