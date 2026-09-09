<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait XcityUrlDetectRules
{
    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['xcity.jp'];
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
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, '/avod/detail/')
                    && (ImportRuleHelpers::queryHas($query, 'id') || preg_match('#/avod/detail/[^/]+#i', $path) === 1),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 110,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, '/idol/detail/')
                    && (ImportRuleHelpers::queryHas($query, 'id') || preg_match('#/idol/detail/[^/]+#i', $path) === 1),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 105,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/idol/?$#i')
                    && ImportRuleHelpers::queryHas($query, 'id'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_listing',
                crawlType: CrawlType::PerformerListing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, '/avod/list')
                    || str_contains($query, 'ini=')
                    || str_contains($query, 'kana=')
                    || ImportRuleHelpers::pathMatches($path, '#^/idol/?$#i'),
            ),
        ];
    }
}
