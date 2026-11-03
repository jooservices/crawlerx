<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvWiki;

use JOOservices\CrawlerX\Adapters\AvWiki\EmbeddedTerms;
use PHPUnit\Framework\TestCase;

final class EmbeddedTermsTest extends TestCase
{
    public function test_splits_embedded_terms_by_taxonomy_and_id(): void
    {
        $post = ['_embedded' => ['author' => [['id' => 1]], 'wp:term' => [
            [['id' => 5, 'taxonomy' => 'category', 'name' => 'Maker']],
            [['id' => 9, 'taxonomy' => 'post_tag', 'name' => 'Actress'], ['id' => '10', 'taxonomy' => 'post_tag', 'name' => 'Other']],
        ]]];

        self::assertSame([5], array_keys(EmbeddedTerms::forTaxonomy($post, EmbeddedTerms::CATEGORY)));
        self::assertSame(['9', '10'], array_map('strval', array_keys(EmbeddedTerms::forTaxonomy($post, EmbeddedTerms::TAG))));
        self::assertSame('Actress', EmbeddedTerms::forTaxonomy($post, EmbeddedTerms::TAG)['9']['name']);
    }

    public function test_ignores_malformed_or_missing_embedded_data(): void
    {
        self::assertSame([], EmbeddedTerms::forTaxonomy([], EmbeddedTerms::TAG));
        self::assertSame([], EmbeddedTerms::forTaxonomy(['_embedded' => ['wp:term' => 'x']], EmbeddedTerms::TAG));
        self::assertSame([], EmbeddedTerms::forTaxonomy(['_embedded' => ['wp:term' => ['x', [['id' => 'a', 'taxonomy' => 'post_tag'], 'y']]]], EmbeddedTerms::TAG));
    }
}
