<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Digitalark;

final class Site
{
    public const NAME = 'DIGITAL ARK';

    public static function itemId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/view/item/(\d+)/?$#', $path, $match) === 1 ? $match[1] : null;
    }
}
