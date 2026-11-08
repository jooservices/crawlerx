<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

trait UrlDetectRules
{
    private const SECTIONS = 'censored|uncensored|western|fc2|chinese|hanime';

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
                matcher: static fn(string $url, string $path, string $query): bool => Site::workId($url) !== null,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: static fn(string $url, string $path, string $query): bool => preg_match(
                    '#^/(?:(?:' . self::SECTIONS . ')(?:/(?:maker|series|genre|director)/[^/]+)?|code/[^/]+)$#',
                    Site::path($url),
                ) === 1,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => Site::performerId($url) !== null,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_listing',
                crawlType: CrawlType::PerformerListing,
                priority: 50,
                matcher: static fn(string $url, string $path, string $query): bool => preg_match(
                    '#^/(?:censored|uncensored|western)/actor$#',
                    Site::path($url),
                ) === 1,
            ),
        ];
    }
}
