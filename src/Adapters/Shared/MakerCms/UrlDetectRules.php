<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

trait UrlDetectRules
{
    abstract protected function siteConfig(): SiteConfig;

    /** @return list<string> */
    protected function urlDetectHostPatterns(): array
    {
        return [];
    }

    /** @return list<ImportMatchRule> */
    protected function urlDetectRules(): array
    {
        $config = $this->siteConfig();

        return [
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'detail',
                crawlType: CrawlType::Detail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => preg_match('#^' . preg_quote($config->detailPath, '#') . '[A-Za-z0-9_-]+/?$#', $path) === 1,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => preg_match('#^' . preg_quote($config->actressPath, '#') . '\d+/?$#', $path) === 1,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 80,
                matcher: static fn(string $url, string $path, string $query): bool => rtrim($path, '/') === $config->datePath
                    || preg_match('#^' . preg_quote($config->listingPath, '#') . '\d{4}-\d{2}-\d{2}/?$#', $path) === 1
                    || preg_match('#^' . preg_quote($config->groupListPath, '#') . '(?:genre|label|series)/\d+/?$#', $path) === 1,
            ),
        ];
    }
}
