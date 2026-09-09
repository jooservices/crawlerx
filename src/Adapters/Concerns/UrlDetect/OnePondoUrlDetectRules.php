<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait OnePondoUrlDetectRules
{
    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return [];
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
        ];
    }
}
