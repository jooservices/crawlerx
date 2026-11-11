<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tokuma;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

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
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/book/b\d+\.html$#'),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: static function (string $url, string $path, string $query): bool {
                    if ($path === '/search/new.html') {
                        return $query === '';
                    }
                    if ($path !== '/search/index.php') {
                        return false;
                    }

                    $params = [];
                    parse_str($query, $params);
                    $page = $params['page'] ?? '1';

                    return ($params['new_status'] ?? null) === 'true'
                        && ($params['c'] ?? null) === '1'
                        && array_diff(array_keys($params), ['page', 'new_status', 'c']) === []
                        && is_string($page)
                        && ctype_digit($page)
                        && (int) $page > 0;
                },
            ),
        ];
    }
}
