<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira;

final class Site
{
    public const BASE_URL = 'https://yuira.jp';

    public const NAME = 'YUIRA';

    /** The product id is the decoded product slug: `/product/<slug>/` gives `<slug>`, or null for any other path. */
    public static function productId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/product/([^/]+)/?$#', $path, $match) === 1
            ? rawurldecode($match[1])
            : null;
    }
}
