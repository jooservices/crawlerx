<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait JavBusUrlDetectRules
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
                urlType: 'performer_listing',
                crawlType: CrawlType::PerformerListing,
                priority: 105,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/(?:en/)?(?:actresses|stars)(?:/\d+|/page/\d+)?/?$#i',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 110,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/(?:en/)?star/[^/]+/?$#i',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'import_detail',
                crawlType: CrawlType::Detail,
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/(?:en/)?[A-Za-z0-9]+-[0-9]+/?$#',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 40,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/(?:en)?/?$#i',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/(?:en/)?(genre|studio|search|uncensored|censored|director|label|series|page)(/|$)#i',
                ),
            ),
        ];
    }
}
