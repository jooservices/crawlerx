<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons;

/**
 * URL helpers for the Wikimedia Commons MediaWiki API. Page URLs (`/wiki/Category:X`, `/wiki/File:X`) and
 * API URLs both resolve to the same target, so a crawl can start from either.
 */
final class Site
{
    public const BASE_URL = 'https://commons.wikimedia.org';

    public const ROOT_CATEGORY = 'Category:Gravure idols from Japan';

    public const MAX_LIMIT = 50;

    private const API_URL = self::BASE_URL . '/w/api.php';

    private const IMAGE_INFO = 'timestamp|user|userid|comment|parsedcomment|canonicaltitle|url|size|dimensions|sha1|mime|mediatype|bitdepth|extmetadata';

    private const MEMBER_PROPS = 'ids|title|type|timestamp|sortkeyprefix';

    /**
     * @return array{title: string, continue: ?string, limit: int}|null
     */
    public static function target(string $url): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';
        $query = [];
        if (is_string($parts['query'] ?? null)) {
            parse_str($parts['query'], $query);
        }

        $title = self::titleFromPath($path) ?? self::titleFromQuery($query);
        if ($title === null) {
            return null;
        }

        $continue = $query['cmcontinue'] ?? null;
        $limit = $query['cmlimit'] ?? null;

        return [
            'title' => $title,
            'continue' => is_string($continue) && $continue !== '' ? $continue : null,
            'limit' => is_numeric($limit) ? max(1, min(self::MAX_LIMIT, (int) $limit)) : self::MAX_LIMIT,
        ];
    }

    public static function isCategory(string $title): bool
    {
        return str_starts_with($title, 'Category:');
    }

    public static function isRoot(string $title): bool
    {
        return $title === self::ROOT_CATEGORY;
    }

    public static function pageUrl(string $title): string
    {
        return self::BASE_URL . '/wiki/' . str_replace('%3A', ':', rawurlencode(str_replace(' ', '_', $title)));
    }

    /** Ordered member list of a category (sub-categories and/or files). */
    public static function membersUrl(string $title, string $types, ?string $continue, int $limit): string
    {
        $query = self::base() + [
            'list' => 'categorymembers',
            'cmtitle' => $title,
            'cmtype' => $types,
            'cmlimit' => $limit,
            'cmprop' => self::MEMBER_PROPS,
        ];
        if ($continue !== null) {
            $query['cmcontinue'] = $continue;
        }

        return self::build($query);
    }

    /** Full data of the given members (images and/or categories), looked up by page id. */
    public static function pagesUrl(string $pageIds): string
    {
        return self::build(self::base() + ['pageids' => $pageIds] + self::pageProps());
    }

    /** Full data of one File: page. */
    public static function fileUrl(string $title): string
    {
        return self::build(self::base() + ['titles' => $title] + self::pageProps());
    }

    /**
     * The category page itself (person entity), optionally with its ordered file members in the same request.
     */
    public static function categoryUrl(string $title, ?string $members = null, ?string $continue = null, int $limit = self::MAX_LIMIT): string
    {
        $query = self::base() + [
            'titles' => $title,
            'prop' => 'categoryinfo|pageprops|pageimages|info|revisions|categories|langlinks|extlinks',
            'inprop' => 'url',
            'piprop' => 'original|thumbnail|name',
            'pithumbsize' => 320,
            'rvprop' => 'content|timestamp',
            'rvslots' => 'main',
            'cllimit' => 'max',
            'lllimit' => 'max',
            'ellimit' => 'max',
        ];
        if ($members !== null) {
            $query += [
                'list' => 'categorymembers',
                'cmtitle' => $title,
                'cmtype' => $members,
                'cmlimit' => $limit,
                'cmprop' => self::MEMBER_PROPS,
            ];
            if ($continue !== null) {
                $query['cmcontinue'] = $continue;
            }
        }

        return self::build($query);
    }

    /** Strips the tracking query Commons appends to media URLs, keeping the stable file URL. */
    public static function cleanMediaUrl(string $url): string
    {
        $clean = preg_replace('/\?utm_[^#]*$/', '', $url);

        return is_string($clean) ? $clean : $url;
    }

    public static function specialFilePath(string $fileName): string
    {
        return self::BASE_URL . '/wiki/Special:FilePath/' . rawurlencode(str_replace(' ', '_', $fileName));
    }

    /** @return array<string, string> */
    private static function base(): array
    {
        return ['action' => 'query', 'format' => 'json', 'formatversion' => '2'];
    }

    /** @return array<string, string|int> */
    private static function pageProps(): array
    {
        return [
            'prop' => 'imageinfo|categoryinfo|pageprops|pageimages|info|categories',
            'iiprop' => self::IMAGE_INFO,
            'iiurlwidth' => 320,
            'iiextmetadatalanguage' => 'en',
            'piprop' => 'original|thumbnail|name',
            'pithumbsize' => 320,
            'inprop' => 'url',
            'cllimit' => 'max',
        ];
    }

    /** @param array<string, string|int> $query */
    private static function build(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = $key . '=' . urlencode((string) $value);
        }

        return self::API_URL . '?' . implode('&', $parts);
    }

    private static function titleFromPath(string $path): ?string
    {
        if (preg_match('#^/wiki/((?:Category|File):.+)$#i', $path, $match) !== 1) {
            return null;
        }

        return self::normalizeTitle(rawurldecode($match[1]));
    }

    /** @param array<mixed> $query */
    private static function titleFromQuery(array $query): ?string
    {
        foreach (['title', 'cmtitle', 'titles'] as $key) {
            $value = $query[$key] ?? null;
            if (is_string($value) && preg_match('/^(Category|File):/i', $value) === 1) {
                return self::normalizeTitle($value);
            }
        }

        return null;
    }

    private static function normalizeTitle(string $title): string
    {
        $title = trim(str_replace('_', ' ', $title));
        $colon = strpos($title, ':');
        if ($colon === false) {
            return $title;
        }

        return ucfirst(strtolower(substr($title, 0, $colon))) . ':' . ltrim(substr($title, $colon + 1));
    }
}
