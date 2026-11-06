<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Rocket;

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
                matcher: static fn(string $url, string $path, string $query): bool => preg_match('#^/works/(?!page/)[^/]+/?$#', $path) === 1,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 80,
                matcher: static fn(string $url, string $path, string $query): bool => preg_match('#^/works(?:/page/\d+)?/?$#', $path) === 1
                    || preg_match('#^/works_(?:actress|genre|director|series)/[^/]+(?:/page/\d+)?/?$#', $path) === 1,
            ),
        ];
    }
}
