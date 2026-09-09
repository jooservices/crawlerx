<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait JableUrlDetectRules
{
    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['jable.tv'];
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
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/videos/[^/]+/?$#i',
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathContains($path, '/tags/')
                    || ImportRuleHelpers::pathContains($path, '/categories/')
                    || ImportRuleHelpers::pathContains($path, '/new-release')
                    || ImportRuleHelpers::pathContains($path, '/hot/')
                    || ImportRuleHelpers::pathContains($path, '/actresses/'),
            ),
        ];
    }
}
