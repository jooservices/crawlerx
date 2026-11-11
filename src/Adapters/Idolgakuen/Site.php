<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Idolgakuen;

final class Site
{
    public const NAME = 'Idol Gakuen';

    public const BASE_URL = 'https://idol-gakuen.jp';

    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/item/(\d+)/?$#', $path, $match) === 1 ? $match[1] : null;
    }
}
