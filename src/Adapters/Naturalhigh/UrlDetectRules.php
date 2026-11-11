<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Naturalhigh;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

trait UrlDetectRules
{
    /** @return list<string> */
    protected function urlDetectHostPatterns(): array
    {
        return ['naturalhigh.co.jp'];
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
                matcher: static fn(string $url, string $path, string $query): bool => preg_match('#^/all/[a-z0-9]+-[a-z0-9-]+/?$#i', $path) === 1,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 80,
                matcher: static function (string $url, string $path, string $query): bool {
                    parse_str($query, $params);

                    return preg_match('#^/all/?$#', $path) === 1
                        && (! isset($params['sf_paged']) || (is_string($params['sf_paged']) && ctype_digit($params['sf_paged'])));
                },
            ),
        ];
    }
}
