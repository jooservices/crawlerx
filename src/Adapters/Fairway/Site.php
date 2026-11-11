<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fairway;

final class Site
{
    public const NAME = 'FAIR＆WAY';

    public const BASE_URL = 'https://fairway-av.com';

    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/works/detail/([A-Za-z0-9_-]+)/?$#', $path, $match) === 1
            ? $match[1]
            : null;
    }
}
