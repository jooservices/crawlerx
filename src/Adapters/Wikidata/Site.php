<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata;

/**
 * URL building and recognition for the official Wikidata endpoints: the Wikidata Query Service (listing) and the
 * wbgetentities API (entities and labels).
 */
final class Site
{
    public const NAME = 'Wikidata';

    public const BASE_URL = 'https://www.wikidata.org';

    public const API_URL = self::BASE_URL . '/w/api.php';

    public const SPARQL_URL = 'https://query.wikidata.org/sparql';

    /** wbgetentities accepts at most 50 ids per request. */
    public const MAX_IDS = 50;

    public const DEFAULT_LIMIT = 50;

    private const COMMONS_FILE_PATH = 'https://commons.wikimedia.org/wiki/Special:FilePath/';

    private const ENTITY_PATH = '#^/wiki/(?:Special:EntityData/)?(Q[1-9]\d*)(?:\.json)?$#';

    /** Japanese pornographic film actors (occupation Q488111, country of citizenship Japan Q17). */
    private const LISTING_QUERY = 'SELECT ?p WHERE { ?p wdt:P106 wd:Q488111; wdt:P27 wd:Q17. } ORDER BY ?p LIMIT %d OFFSET %d';

    public static function listingUrl(int $offset = 0, int $limit = self::DEFAULT_LIMIT): string
    {
        $query = sprintf(self::LISTING_QUERY, max(1, $limit), max(0, $offset));

        return self::SPARQL_URL . '?' . http_build_query(['query' => $query, 'format' => 'json'], '', '&', PHP_QUERY_RFC3986);
    }

    public static function isListing(string $url): bool
    {
        return parse_url($url, PHP_URL_HOST) === 'query.wikidata.org' && parse_url($url, PHP_URL_PATH) === '/sparql';
    }

    public static function offset(string $url): int
    {
        return self::integerFromQuery($url, '/\bOFFSET\s+(\d+)/i') ?? 0;
    }

    public static function limit(string $url): int
    {
        return max(1, self::integerFromQuery($url, '/\bLIMIT\s+(\d+)/i') ?? self::DEFAULT_LIMIT);
    }

    /** The Q-id an entity page, entity-data URL or wbgetentities URL points at. */
    public static function qid(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match(self::ENTITY_PATH, $path, $match) === 1) {
            return $match[1];
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $ids = $query['ids'] ?? $query['id'] ?? null;

        return is_string($ids) && preg_match('/^Q[1-9]\d*$/', $ids) === 1 ? $ids : null;
    }

    public static function entityPageUrl(string $qid): string
    {
        return self::BASE_URL . '/wiki/' . $qid;
    }

    /** @param list<string> $ids */
    public static function entitiesUrl(array $ids): string
    {
        return self::apiUrl([
            'action' => 'wbgetentities',
            'ids' => implode('|', $ids),
            'props' => 'labels|descriptions|aliases|claims|sitelinks/urls',
            'languages' => 'ja|en',
            'sitefilter' => 'jawiki|enwiki',
            'format' => 'json',
        ]);
    }

    /** @param list<string> $ids Q-ids and P-ids whose labels are wanted */
    public static function labelsUrl(array $ids): string
    {
        return self::apiUrl([
            'action' => 'wbgetentities',
            'ids' => implode('|', $ids),
            'props' => 'labels',
            'languages' => 'ja|en',
            'format' => 'json',
        ]);
    }

    /** The original file of a Commons media name (a statement value of datatype commonsMedia). */
    public static function fileUrl(string $file): string
    {
        return self::COMMONS_FILE_PATH . rawurlencode(str_replace(' ', '_', $file));
    }

    public static function thumbnailUrl(string $file, int $width = 320): string
    {
        return self::fileUrl($file) . '?width=' . $width;
    }

    /** @param array<string, string> $params */
    private static function apiUrl(array $params): string
    {
        return self::API_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private static function integerFromQuery(string $url, string $pattern): ?int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $sparql = $params['query'] ?? null;

        return is_string($sparql) && preg_match($pattern, $sparql, $match) === 1 ? (int) $match[1] : null;
    }
}
