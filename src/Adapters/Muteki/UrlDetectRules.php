<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muteki;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

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
                matcher: static fn(string $url, string $path, string $query): bool => preg_match('#^/works/detail/[A-Za-z0-9_-]+/?$#', $path) === 1,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 80,
                matcher: static fn(string $url, string $path, string $query): bool => preg_match('#^/works/list/(?:release|all|year/\d{4}|genre/\d+)(?:/[1-9]\d*)?/?$#', $path) === 1,
            ),
        ];
    }
}
