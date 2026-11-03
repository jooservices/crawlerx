<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

/**
 * Shared URL detection for the 1Pondo family (OnePondo, 10musume, ...). The rules and host patterns are built from the
 * site's SiteConfig so every family member infers the same detail and listing crawl types.
 */
trait UrlDetectRules
{
    abstract protected function siteConfig(): SiteConfig;

    /**
     * Accept the bare registrable domain in addition to the manifest host, so the www and root forms of the base URL
     * both resolve to this adapter during automatic site selection.
     *
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        $host = ImportRuleHelpers::hostFromUrl($this->siteConfig()->baseUrl);
        if ($host === null) {
            return [];
        }

        $labels = explode('.', $host);
        if (count($labels) < 2) {
            return [];
        }

        return [implode('.', array_slice($labels, -2))];
    }

    /**
     * @return list<ImportMatchRule>
     */
    protected function urlDetectRules(): array
    {
        return [
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'import_detail',
                crawlType: CrawlType::Detail,
                priority: 120,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, 'movie_details')
                    || ImportRuleHelpers::pathContains($path, 'movie_id'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'import_detail',
                crawlType: CrawlType::Detail,
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/movies/[^/]+/?$#i',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, 'movie_lists')
                    || ImportRuleHelpers::pathContains($path, 'dyn/phpauto'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/list/?$#i',
                ),
            ),
        ];
    }
}
