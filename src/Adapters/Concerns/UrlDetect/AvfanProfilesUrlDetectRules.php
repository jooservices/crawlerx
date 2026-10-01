<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait AvfanProfilesUrlDetectRules
{
    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['av-fan.tokyo'];
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
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/actress/[^/]+\.html$#i',
                ) && ! ImportRuleHelpers::pathContains($path, '/actress/cup/')
                    && ! ImportRuleHelpers::pathContains($path, '/actress/birthday/')
                    && ! ImportRuleHelpers::pathContains($path, '/actress/area/')
                    && ! ImportRuleHelpers::pathContains($path, '/actress/bust/')
                    && ! ImportRuleHelpers::pathContains($path, '/actress/waist/')
                    && ! ImportRuleHelpers::pathContains($path, '/actress/hip/'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_listing',
                crawlType: CrawlType::PerformerListing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains(
                    $path,
                    '/actress/cup/',
                ),
            ),
        ];
    }
}
