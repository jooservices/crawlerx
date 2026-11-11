<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Silklabo;

final class Site
{
    public const NAME = 'Silk Labo';

    public const BASE_URL = 'https://www.silklabo.com';

    private const DETAIL_PATH = '#^/detail/(\d+)/?$#';

    private const LISTING_PATH = '#^/product_list(?:/page/\d+)?/?$#';

    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match(self::DETAIL_PATH, $path, $match) === 1 ? $match[1] : null;
    }

    public static function isListing(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match(self::LISTING_PATH, $path) === 1;
    }

    public static function pageNumber(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/product_list/page/(\d+)/?$#', $path, $match) === 1
            ? (int) $match[1]
            : (self::isListing($url) ? 1 : null);
    }

}
