<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravidia;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

trait UrlDetectRules
{
    /** @return list<string> */
    protected function urlDetectHostPatterns(): array
    {
        return ['gravidia.jp'];
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
                matcher: static fn(string $url, string $path, string $query): bool => Site::productId($url) !== null,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 80,
                matcher: static function (string $url, string $path, string $query): bool {
                    if (preg_match('#^/search(?:/page/[1-9]\d*)?/?$#', $path) !== 1) {
                        return false;
                    }

                    parse_str($query, $params);

                    return ($params['post_type'] ?? null) === 'product' && ($params['cts'] ?? null) === 'cts';
                },
            ),
        ];
    }
}
