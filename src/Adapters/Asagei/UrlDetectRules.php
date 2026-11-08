<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Asagei;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait UrlDetectRules
{
    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['asagei.com'];
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
                    Urls::ARTICLE_PATH,
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Gallery,
                urlType: 'gallery_listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    Urls::LISTING_PATH,
                ),
            ),
        ];
    }
}
