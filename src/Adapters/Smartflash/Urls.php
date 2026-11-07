<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Smartflash;

final class Urls
{
    /** Article page, or one of its photo-viewer pages: /gravure/idol/432913/ and /gravure/idol/432913/image/1/. */
    public const ARTICLE_PATH = '#^/gravure/[a-z0-9-]+/\d{5,}/(?:image/\d+/)?$#i';

    /** The article address without the tracking query and without the photo-viewer suffix. */
    public static function article(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($path) || ! is_string($host) || ! is_string($scheme)) {
            return $url;
        }

        $path = (string) preg_replace('#(?<=/)image/\d+/?$#', '', $path);

        return $scheme . '://' . $host . $path;
    }

    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/gravure/[a-z0-9-]+/(\d{5,})(?:/|$)#i', $path, $match) === 1
            ? $match[1]
            : null;
    }

    /** The upload without its "-190x127" size suffix, i.e. the original file the thumbnail was cut from. */
    public static function original(string $imageUrl): string
    {
        return (string) preg_replace('#-\d+x\d+(\.[a-z]{3,4})$#i', '$1', $imageUrl);
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
