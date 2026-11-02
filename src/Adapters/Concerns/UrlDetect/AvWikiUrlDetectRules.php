<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait AvWikiUrlDetectRules
{
    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['av-wiki.net'];
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
                priority: 120,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches(
                    $path,
                    '#^/wp-json/wp/v2/posts/\d+/?$#i',
                ) || (ImportRuleHelpers::pathContains($path, '/wp-json/wp/v2/posts')
                    && (ImportRuleHelpers::queryHas($query, 'slug') || ImportRuleHelpers::queryHas($query, 'id'))),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'import_detail',
                crawlType: CrawlType::Detail,
                priority: 110,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::queryHas($query, 'p')
                    || (ImportRuleHelpers::singleSegmentSlug($path, [
                        'author',
                        'av-actress',
                        'category',
                        'fanza-video',
                        'mgstage',
                        'page',
                        'tag',
                        'wp-json',
                    ]) && preg_match('/^\d+$/', trim($path, '/')) !== 1),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: static fn(string $url, string $path, string $query): bool => $path === '/'
                    || ImportRuleHelpers::pathMatches($path, '#^/page/\d+/?$#i')
                    || ImportRuleHelpers::pathMatches($path, '#^/list/?$#i')
                    || ImportRuleHelpers::pathMatches($path, '#^/wp-json/wp/v2/posts/?$#i'),
            ),
        ];
    }
}
