<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

final class TaxonomyResolver
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @return array<int|string, array<string, mixed>>
     */
    public function tags(array $posts): array
    {
        return $this->resolve($posts, 'tags', 'tags');
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @return array<int|string, array<string, mixed>>
     */
    public function categories(array $posts): array
    {
        return $this->resolve($posts, 'categories', 'categories');
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @return array<int|string, array<string, mixed>>
     */
    private function resolve(array $posts, string $field, string $taxonomy): array
    {
        $ids = [];
        foreach ($posts as $post) {
            $values = $post[$field] ?? null;
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0) {
                    $ids[] = (string) $value;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        if ($ids === []) {
            return [];
        }

        $response = $this->client->get(Site::taxonomyUrl($taxonomy, $ids))->toPsrResponse();
        $records = AvWikiJson::records(AvWikiJson::decode($response, $taxonomy), $taxonomy);

        $indexed = [];
        foreach ($records as $record) {
            $id = $record['id'] ?? null;
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $indexed[(string) $id] = $record;
            }
        }

        $missing = array_values(array_diff($ids, array_keys($indexed)));
        if ($missing !== []) {
            throw new CrawlParseException("AV-Wiki {$taxonomy} response omitted requested taxonomy terms.");
        }

        return $indexed;
    }
}
