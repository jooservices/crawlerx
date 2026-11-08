<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avactress;

use JOOservices\CrawlerX\Support\DmmCover;

final class Site
{
    public const NAME = 'AV-Actress';

    public const BASE_URL = 'https://av-actress.com';

    private const PERFORMER_PATH = '#^/(\d+)/[^/]+/?$#u';

    /** The site's own performer id: the first path segment of `/<id>/<name>`, for example `26225`. */
    public static function performerId(string $url): ?string
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));

        return preg_match(self::PERFORMER_PATH, $path, $match) === 1 ? $match[1] : null;
    }

    /**
     * The page serves each cover as a small `…ps.jpg` thumbnail; the same CDN folder holds the large `…pl.jpg`.
     */
    public static function largeCover(string $thumbnail): string
    {
        return DmmCover::large($thumbnail);
    }

    /** The FANZA content id of a work link: the `id` of the `lurl` target of the affiliate URL, for example `snos00422`. */
    public static function contentId(string $affiliateUrl): ?string
    {
        parse_str((string) parse_url($affiliateUrl, PHP_URL_QUERY), $query);
        $target = is_string($query['lurl'] ?? null) ? $query['lurl'] : '';
        parse_str((string) parse_url($target, PHP_URL_QUERY), $targetQuery);

        return is_string($targetQuery['id'] ?? null) && $targetQuery['id'] !== '' ? $targetQuery['id'] : null;
    }
}
