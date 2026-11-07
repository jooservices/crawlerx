<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Materiall;

final class Site
{
    public const NAME = 'Materiall';

    public const BASE_URL = 'https://materiall.jp';

    private const WORK_PATH = '#^/works/([^/]+)/?$#';

    /**
     * The site's own work id: the slug of `/works/<slug>/`, for example `mtall-149`.
     */
    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match(self::WORK_PATH, $path, $match) === 1 ? $match[1] : null;
    }

    /**
     * The catalogue code shown for a work: its id in capitals, for example `MTALL-149`.
     */
    public static function code(string $externalId): string
    {
        return strtoupper($externalId);
    }

    /**
     * Cast names are printed one after another, separated by spaces (cards) or enumeration marks (detail rows).
     *
     * @return list<string>
     */
    public static function castNames(?string $cast): array
    {
        $names = preg_split('#[\s　、,，/／]+#u', (string) $cast, -1, PREG_SPLIT_NO_EMPTY);

        return $names === false ? [] : $names;
    }

    /**
     * The title the page was published under, without the " | Materiall | <tagline>" suffix of the page title.
     */
    public static function cleanTitle(?string $title): ?string
    {
        $clean = trim((string) preg_replace('/\s*\|\s*' . self::NAME . '\b.*$/u', '', (string) $title));

        return $clean === '' ? null : $clean;
    }
}
