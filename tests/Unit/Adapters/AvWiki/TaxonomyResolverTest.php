<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvWiki;

use JOOservices\CrawlerX\Adapters\AvWiki\TaxonomyResolver;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;

final class TaxonomyResolverTest extends CrawlerXTestCase
{
    public function test_returns_no_terms_without_post_taxonomy_ids(): void
    {
        self::assertSame([], (new TaxonomyResolver($this->crawlHttpClient()))->tags([['id' => 1]]));
    }

    public function test_indexes_requested_terms_by_id(): void
    {
        $url = 'https://av-wiki.net/wp-json/wp/v2/tags?include=128919';
        $this->respondWithFixture('GET', $url, 'avwiki/tags-detail-200gana-3482.json');
        $terms = (new TaxonomyResolver($this->crawlHttpClient()))->tags([['tags' => [128919]]]);

        self::assertSame([128919], array_keys($terms));
    }

    public function test_rejects_missing_requested_terms(): void
    {
        $url = 'https://av-wiki.net/wp-json/wp/v2/categories?include=42';
        $this->respondWithFixture('GET', $url, 'avwiki/categories-list-page1.json');

        $this->expectException(CrawlParseException::class);
        (new TaxonomyResolver($this->crawlHttpClient()))->categories([['categories' => [42]]]);
    }
}
