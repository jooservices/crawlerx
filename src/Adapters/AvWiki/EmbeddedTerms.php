<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki;

/**
 * Reads the taxonomy terms WordPress embeds in a post when `_embed=1` is requested
 * (`_embedded['wp:term']`), so categories and tags never need their own API requests.
 */
final class EmbeddedTerms
{
    public const CATEGORY = 'category';

    public const TAG = 'post_tag';

    /**
     * @param  array<string, mixed>  $post
     * @return array<int|string, array<string, mixed>>  terms of one taxonomy, keyed by term ID
     */
    public static function forTaxonomy(array $post, string $taxonomy): array
    {
        $embedded = $post['_embedded'] ?? null;
        $groups = is_array($embedded) && is_array($embedded['wp:term'] ?? null) ? $embedded['wp:term'] : [];
        $terms = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            foreach ($group as $term) {
                if (! is_array($term) || ($term['taxonomy'] ?? null) !== $taxonomy) {
                    continue;
                }

                $id = $term['id'] ?? null;
                if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                    continue;
                }

                $fields = [];
                foreach ($term as $key => $value) {
                    if (is_string($key)) {
                        $fields[$key] = $value;
                    }
                }
                $terms[(string) $id] = $fields;
            }
        }

        return $terms;
    }
}
