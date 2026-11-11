<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravidia;

final class Site
{
    public const NAME = 'Gravidia';

    public static function productId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/product/(\d+)/?$#', $path, $match) === 1 ? $match[1] : null;
    }
}
