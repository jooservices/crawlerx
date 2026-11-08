<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait UrlDetectRules
{
    /**
     * The Query Service host serves the listing; the manifest host serves items.
     *
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['query.wikidata.org'];
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
                matcher: static fn(string $url, string $path, string $query): bool => $path === '/sparql',
            ),
            new ImportMatchRule(
                entity: ImportEntity::Performer,
                urlType: 'performer_detail',
                crawlType: CrawlType::PerformerDetail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool => ImportRuleHelpers::pathMatches($path, '#^/wiki/(?:Special:EntityData/)?Q[1-9]\d*(?:\.json)?$#')
                    || (str_starts_with($path, '/w/api.php') && Site::qid($url) !== null),
            ),
        ];
    }
}
