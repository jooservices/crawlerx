<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia;

use JOOservices\CrawlerX\Adapters\Falenogroup\Site as FalenoSite;

final class Site
{
    public const NAME = 'DAHLIA';

    public const BASE_URL = 'https://dahlia-av.jp';

    private const WORK_PATH = '#^/works/([^/]+)/?$#';

    private const PERFORMER_PATH = '#^/actress/([^/]+)/?$#';

    /** The site's own work id: the slug of `/works/<slug>/`, for example `dldss573`. */
    public static function workId(string $url): ?string
    {
        return self::slug($url, self::WORK_PATH);
    }

    /** The site's own performer id: the slug of `/actress/<slug>/`, for example `naho_ozawa`. */
    public static function performerId(string $url): ?string
    {
        return self::slug($url, self::PERFORMER_PATH);
    }

    /** The catalogue code of a work: letters and number of its id, for example `dldss573` becomes `DLDSS-573`. */
    public static function code(string $workId): string
    {
        return preg_match('/^([a-z]+)(\d+)$/i', $workId, $match) === 1
            ? strtoupper($match[1]) . '-' . $match[2]
            : strtoupper($workId);
    }

    /**
     * The CDN re-compresses images through `resize` and `output-quality` query parameters; the same shared
     * theme family (FALENO GROUP) strips them to reach the original upload.
     */
    public static function originalImage(string $url): string
    {
        return FalenoSite::originalImage($url);
    }

    private static function slug(string $url, string $pattern): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match($pattern, $path, $match) === 1 ? $match[1] : null;
    }
}
