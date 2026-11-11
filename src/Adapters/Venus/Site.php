<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Venus;

final class Site
{
    public const NAME = 'VENUS';

    public const BASE_URL = 'https://venus-av.com';

    public static function isListingPath(string $path): bool
    {
        return preg_match('#^/all(?:/page/\d+)?/?$#', $path) === 1;
    }

    public static function isDetailPath(string $path): bool
    {
        return preg_match('#^/products/[^/]+/?$#', $path) === 1;
    }

    public static function code(?string $value): ?string
    {
        return $value !== null && preg_match('/\b([A-Z]{2,}-\d+)\b/i', $value, $match) === 1
            ? strtoupper($match[1])
            : null;
    }
}
