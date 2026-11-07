<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * Reads MediaWiki API responses into plain arrays.
 */
final class CommonsApi
{
    /**
     * @return array<string, mixed>
     */
    public static function query(CrawlHttpClient $client, string $url, string $context): array
    {
        $response = $client->get($url)->toPsrResponse();
        $body = (string) $response->getBody();
        if ($response->getStatusCode() >= 400 || trim($body) === '') {
            throw new CrawlBlockedException("Commons {$context} response is blocked or unavailable.");
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded) || ! is_array($decoded['query'] ?? null)) {
            throw new CrawlParseException("Commons {$context} response is not a MediaWiki query result.");
        }

        return self::assoc($decoded);
    }

    /**
     * Pages of a query result keyed by page id (missing pages are dropped).
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public static function pages(array $payload): array
    {
        $query = self::assoc($payload['query'] ?? []);
        $pages = [];
        foreach (is_array($query['pages'] ?? null) ? $query['pages'] : [] as $page) {
            if (is_array($page) && is_int($page['pageid'] ?? null) && ! isset($page['missing'])) {
                $pages[$page['pageid']] = self::assoc($page);
            }
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{pageid: int, title: string, type: mixed, timestamp: mixed, sortkeyprefix: mixed}>
     */
    public static function members(array $payload): array
    {
        $query = self::assoc($payload['query'] ?? []);
        $members = [];
        foreach (is_array($query['categorymembers'] ?? null) ? $query['categorymembers'] : [] as $member) {
            $member = self::assoc($member);
            if (is_int($member['pageid'] ?? null) && is_string($member['title'] ?? null)) {
                $members[] = [
                    'pageid' => $member['pageid'],
                    'title' => $member['title'],
                    'type' => $member['type'] ?? null,
                    'timestamp' => $member['timestamp'] ?? null,
                    'sortkeyprefix' => $member['sortkeyprefix'] ?? null,
                ];
            }
        }

        return $members;
    }

    /**
     * @param  list<array{pageid: int, title: string, type: mixed, timestamp: mixed, sortkeyprefix: mixed}>  $members
     */
    public static function pageIds(array $members): string
    {
        return implode('|', array_map(static fn(array $member): int => $member['pageid'], $members));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function continuation(array $payload): ?string
    {
        $continue = self::assoc($payload['continue'] ?? []);
        $token = $continue['cmcontinue'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function assoc(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $entry) {
            if (is_string($key)) {
                $result[$key] = $entry;
            }
        }

        return $result;
    }
}
