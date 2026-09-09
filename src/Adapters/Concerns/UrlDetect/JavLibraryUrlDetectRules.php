<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait JavLibraryUrlDetectRules
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
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 110,
                matcher: fn(string $url, string $path, string $query): bool => (
                    ImportRuleHelpers::pathContains($path, 'star_info.php') && ImportRuleHelpers::queryHas($query, 'st')
                ) || (
                    ImportRuleHelpers::pathContains($path, 'vl_star.php') && ImportRuleHelpers::queryHas($query, 's')
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'import_detail',
                crawlType: CrawlType::Detail,
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::queryHas($query, 'v')
                    || ImportRuleHelpers::pathMatches($path, '#/[a-z0-9]+\.html$#i'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, 'star_list.php')
                    || ImportRuleHelpers::pathContains($path, 'vl_searchbyiduse.php')
                    || ImportRuleHelpers::pathContains($path, 'vl_newrelease.php'),
            ),
        ];
    }
}
