<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Plum;

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
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/title/[^/]+/?$#'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^(?:/|/(?:series|category)/[^/]+|/ym/\d{6})?(?:/page/\d+)?/?$#',
                ),
            ),
        ];
    }
}
