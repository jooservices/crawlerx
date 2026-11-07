<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dandy;

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
        return ['choi-waru.com'];
    }

    /**
     * @return list<ImportMatchRule>
     */
    protected function urlDetectRules(): array
    {
        $labels = Site::labelPattern();

        return [
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'detail',
                crawlType: CrawlType::Detail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => Site::workSlug($path) !== null,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 80,
                matcher: static fn(string $url, string $path, string $query): bool => $path === '/'
                    || ImportRuleHelpers::pathMatches($path, '#^/(?:' . $labels . ')/(?:works/(?:page/\d+/)?)?$#i')
                    || ImportRuleHelpers::pathMatches($path, '#^/(?:' . $labels . ')/genre/[^/]+/(?:page/\d+/)?$#i'),
            ),
        ];
    }
}
