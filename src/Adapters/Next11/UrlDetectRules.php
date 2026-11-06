<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Next11;

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
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/products/detail\.php$#')
                    && ImportRuleHelpers::queryHas($query, 'product_id'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 80,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/products/list\.php$#')
                    && (ImportRuleHelpers::queryHas($query, 'category_id') || ImportRuleHelpers::queryHas($query, 'ym') || ImportRuleHelpers::queryHas($query, 'recommend_search')),
            ),
        ];
    }
}
