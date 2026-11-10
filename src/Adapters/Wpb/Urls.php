<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wpb;

final class Urls
{
    /** Public gravure work page: /gravure/20261108-133432/ */
    public const GALLERY_PATH = '#^/gravure/(\d{8}-\d+)/?$#';

    /** Paged gravure index: /gravure/list/ and /gravure/list/?page=2 */
    public const LISTING_PATH = '#^/gravure/list/?$#';

    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match(self::GALLERY_PATH, $path, $match) === 1
            ? $match[1]
            : null;
    }

    public static function gallery(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($path) || ! is_string($host) || ! is_string($scheme)) {
            return $url;
        }

        if (preg_match(self::GALLERY_PATH, $path, $match) !== 1) {
            return $url;
        }

        return $scheme . '://' . $host . '/gravure/' . $match[1] . '/';
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

    /** Prefer the original /images/ file over an assets_c thumb derivative. */
    public static function preferOriginal(string $imageUrl): string
    {
        if (preg_match('#/assets_c/.+?/([^/]+)-thumb-\d+x\d+-\d+(\.[a-z0-9]+)$#i', $imageUrl, $match) !== 1) {
            return $imageUrl;
        }

        $year = null;
        if (preg_match('#/gravure/(?:news/)?(\d{4})/#', $imageUrl, $yearMatch) === 1) {
            $year = $yearMatch[1];
        }

        $hash = $match[1];
        $ext = $match[2];
        if ($year === null) {
            return $imageUrl;
        }

        $base = preg_match('#/gravure/news/#', $imageUrl) === 1
            ? '/gravure/news/' . $year . '/images/'
            : '/gravure/' . $year . '/images/';

        $parts = parse_url($imageUrl);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $imageUrl;
        }

        return $parts['scheme'] . '://' . $parts['host'] . $base . $hash . $ext;
    }
}
