<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons;

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
        return [];
    }

    /**
     * @return list<ImportMatchRule>
     */
    protected function urlDetectRules(): array
    {
        return [
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 200,
                matcher: static fn(string $url, string $path, string $query): bool => self::isRootPage($path)
                    || (self::param($query, 'list') === 'categorymembers' && self::param($query, 'titles') === null),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 150,
                matcher: static fn(string $url, string $path, string $query): bool => str_starts_with((string) self::param($query, 'titles'), 'Category:')
                    && self::param($query, 'list') === null,
            ),
            new ImportMatchRule(
                entity: ImportEntity::Gallery,
                urlType: 'gallery_detail',
                crawlType: CrawlType::Gallery,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/wiki/(?:Category|File):.+#i')
                    || preg_match('/^(?:Category|File):/', (string) self::param($query, 'titles')) === 1,
            ),
        ];
    }

    private static function isRootPage(string $path): bool
    {
        return str_starts_with($path, '/wiki/')
            && Site::isRoot(str_replace('_', ' ', rawurldecode(substr($path, strlen('/wiki/')))));
    }

    private static function param(string $query, string $name): ?string
    {
        parse_str($query, $params);
        $value = $params[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
