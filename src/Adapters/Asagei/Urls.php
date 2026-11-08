<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Asagei;

final class Urls
{
    public const ARTICLE_PATH = '#^/excerpt/(\d+)/?$#i';

    public const LISTING_PATH = '#^/(?:(?:category|tag)/[^/]+)?/?$#iu';

    /** Article address without tracking or display-mode query strings. */
    public static function article(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($path) || ! is_string($host) || ! is_string($scheme)) {
            return $url;
        }

        return $scheme . '://' . $host . $path;
    }

    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match(self::ARTICLE_PATH, $path, $match) === 1
            ? $match[1]
            : null;
    }

    /** Drop WordPress size suffixes such as `-150x150` to recover the original upload. */
    public static function original(string $imageUrl): string
    {
        return (string) preg_replace('#-\d+x\d+(\.[a-z0-9]{3,4})$#i', '$1', $imageUrl);
    }

    public static function mimeType(string $imageUrl): ?string
    {
        $path = parse_url($imageUrl, PHP_URL_PATH);

        return match (is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '') {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }
}
