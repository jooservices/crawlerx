<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Deeps;

final class Site
{
    public const BASE_URL = 'https://deeps.net';

    public const MAKER = "DEEP'S";

    /** Product code of a detail page (`/item/detail.php?mmgo-020`) or a listing card link. */
    public static function externalId(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && preg_match('/^[A-Za-z0-9_-]+$/', $query) === 1 ? $query : null;
    }

    /** Page number of a listing URL (`index.php?0__50_3`, `index.php?s_<series>_50_3`); null for the first page. */
    public static function pageNumber(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && preg_match('/_50_(\d+)$/', rawurldecode($query), $match) === 1 ? (int) $match[1] : null;
    }
}
