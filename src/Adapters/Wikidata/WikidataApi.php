<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * Reads Wikidata API and Query Service responses into plain arrays.
 */
final class WikidataApi
{
    /**
     * @return array<string, mixed>
     */
    public static function json(CrawlHttpClient $client, string $url, string $context): array
    {
        $response = $client->get($url)->toPsrResponse();
        $body = (string) $response->getBody();
        if ($response->getStatusCode() >= 400 || trim($body) === '') {
            throw new CrawlBlockedException("Wikidata {$context} response is blocked or unavailable.");
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new CrawlParseException("Wikidata {$context} response is not JSON.");
        }

        return self::assoc($decoded);
    }

    /**
     * Q-ids of a SPARQL result, in result order.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function listedIds(array $payload): array
    {
        $results = self::assoc($payload['results'] ?? []);
        $ids = [];
        foreach (is_array($results['bindings'] ?? null) ? $results['bindings'] : [] as $binding) {
            $value = self::assoc(self::assoc($binding)['p'] ?? [])['value'] ?? null;
            if (is_string($value) && preg_match('#/(Q[1-9]\d*)$#', $value, $match) === 1) {
                $ids[] = $match[1];
            }
        }

        return $ids;
    }

    /**
     * Entities of a wbgetentities result keyed by id (missing entities are dropped).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    public static function entities(array $payload): array
    {
        $entities = [];
        foreach (is_array($payload['entities'] ?? null) ? $payload['entities'] : [] as $id => $entity) {
            $entity = self::assoc($entity);
            if (is_string($id) && ! isset($entity['missing'])) {
                $entities[$id] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Labels (Japanese preferred, then English) of the entities of a wbgetentities result keyed by id.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function labels(array $payload): array
    {
        $labels = [];
        foreach (self::entities($payload) as $id => $entity) {
            $label = self::label($entity);
            if ($label !== null) {
                $labels[$id] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public static function label(array $entity, string $language = 'en'): ?string
    {
        $labels = self::assoc($entity['labels'] ?? []);
        $value = self::assoc($labels[$language] ?? [])['value'] ?? self::assoc($labels['ja'] ?? [])['value'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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
