<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait JavPhotosUrlDetectRules
{
    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['jav.photos'];
    }

    /**
     * @return list<ImportMatchRule>
     */
    protected function urlDetectRules(): array
    {
        return [
            new ImportMatchRule(
                entity: ImportEntity::Gallery,
                urlType: 'gallery_detail',
                crawlType: CrawlType::Gallery,
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/free/[^/]+/?$#i',
                ) && ! ImportRuleHelpers::pathMatches($path, '#^/free/\d+/?$#i'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Gallery,
                urlType: 'gallery_listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/free/?$#i',
                ) || ImportRuleHelpers::pathMatches($path, '#^/free/\d+/?$#i'),
            ),
        ];
    }
}
