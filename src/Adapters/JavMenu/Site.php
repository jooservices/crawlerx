<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu;

/**
 * URL helpers for javmenu.com. Every page exists once per interface language (`/en/DSOD-078`, `/ja/actor/83V`); the
 * language prefix is optional and ignored when reading a page's identity.
 */
final class Site
{
    public const NAME = 'JAVMenu';

    public const BASE_URL = 'https://javmenu.com';

    private const LANGUAGE_PREFIX = '#^/(?:zh_TW|zh|en|ja|ko|de|es|fr|ru)(?=/|$)#';

    private const WORK_PATH = '#^/([A-Za-z0-9]+-[A-Za-z0-9_-]+|W\d+)$#';

    private const PERFORMER_PATH = '#^/actor/([^/]+)$#';

    /** The site's own work id: the slug of `/DSOD-078`. */
    public static function workId(string $url): ?string
    {
        return self::match($url, self::WORK_PATH);
    }

    /** The site's own performer id: the slug of `/actor/83V`. */
    public static function performerId(string $url): ?string
    {
        return self::match($url, self::PERFORMER_PATH);
    }

    /** The id of an entity link such as `/censored/maker/440` or `/censored/series/zcka4tsx`. */
    public static function entityId(string $url): ?string
    {
        return self::match($url, '#^/[^/]+/(?:maker|series|director|genre)/([^/]+)$#')
            ?? self::match($url, '#^/code/([^/]+)$#');
    }

    /** The URL path without the interface language prefix. */
    public static function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        $stripped = preg_replace(self::LANGUAGE_PREFIX, '', $path);

        return is_string($stripped) ? $stripped : $path;
    }

    /** The page number of a `?page=N` URL, null when the URL carries none. */
    public static function pageNumber(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $params);
        $page = $params['page'] ?? null;

        return is_string($page) && ctype_digit($page) && (int) $page > 0 ? (int) $page : null;
    }

    private static function match(string $url, string $pattern): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! in_array(strtolower($host), ['javmenu.com', 'www.javmenu.com'], true)) {
            return null;
        }

        return preg_match($pattern, self::path($url), $match) === 1 ? rawurldecode($match[1]) : null;
    }
}
