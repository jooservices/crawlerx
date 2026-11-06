<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Falenogroup;

final class Site
{
    public const NAME = 'FALENO GROUP';

    public const BASE_URL = 'https://falenogroup.com';

    private const WORK_PATH = '#^/works/([^/]+)/?$#';

    /**
     * The site's own work id: the slug of `/works/<slug>/`, for example `fthtd-243`.
     */
    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match(self::WORK_PATH, $path, $match) === 1 ? $match[1] : null;
    }

    /**
     * The catalogue code shown for a work: its id in capitals, for example `FTHTD-243`.
     */
    public static function code(string $externalId): string
    {
        return strtoupper($externalId);
    }

    public static function isMakerPage(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/makers/[^/]+/?$#', $path) === 1;
    }

    /**
     * The CDN serves cards and covers re-compressed through `output-quality` and `resize` query parameters; dropping
     * them yields the original upload at full quality.
     */
    public static function originalImage(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        unset($query['output-quality'], $query['resize']);
        $base = explode('?', $url, 2)[0];

        return $query === [] ? $base : $base . '?' . http_build_query($query);
    }
}
