<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait UrlDetectRules
{
    /** @return list<string> */
    protected function urlDetectHostPatterns(): array
    {
        return [];
    }

    /** @return list<ImportMatchRule> */
    protected function urlDetectRules(): array
    {
        return [
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'detail',
                crawlType: CrawlType::Detail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/works/[^/]+/?$#'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/work(?:/page/\d+)?/?$#'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/actress/[^/]+/?$#'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_listing',
                crawlType: CrawlType::PerformerListing,
                priority: 50,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/actress/?$#'),
            ),
        ];
    }
}
