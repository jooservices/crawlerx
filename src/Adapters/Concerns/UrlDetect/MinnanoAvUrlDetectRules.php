<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait MinnanoAvUrlDetectRules
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
                priority: 110,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/av\d+\.html$#i',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 105,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, 'actress.php')
                    && ImportRuleHelpers::queryHas($query, 'actress_id'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/actress\d+\.html$#i',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'listing',
                crawlType: CrawlType::PerformerListing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, 'actress_list.php')
                    || (ImportRuleHelpers::pathContains($path, 'actress.php') && ! ImportRuleHelpers::queryHas($query, 'actress_id')),
            ),
        ];
    }
}
