<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fairway;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Adapters\Fairway\Types\Detail;
use JOOservices\CrawlerX\Adapters\Fairway\Types\Listing;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class FairwayCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'fairway';
    }

    /** @return list<string> */
    protected function urlDetectHostPatterns(): array
    {
        return ['fairway-av.com'];
    }

    /** @return list<\JOOservices\CrawlerX\Dto\ImportMatchRule> */
    protected function urlDetectRules(): array
    {
        return [
            new \JOOservices\CrawlerX\Dto\ImportMatchRule(
                entity: \JOOservices\CrawlerX\Enums\ImportEntity::Movie,
                urlType: 'detail',
                crawlType: \JOOservices\CrawlerX\Enums\CrawlType::Detail,
                priority: 100,
                matcher: static fn(string $url, string $path, string $query): bool
                    => preg_match('#^/works/detail/[A-Za-z0-9_-]+/?$#', $path) === 1,
            ),
            new \JOOservices\CrawlerX\Dto\ImportMatchRule(
                entity: \JOOservices\CrawlerX\Enums\ImportEntity::Movie,
                urlType: 'listing',
                crawlType: \JOOservices\CrawlerX\Enums\CrawlType::Listing,
                priority: 80,
                matcher: static fn(string $url, string $path, string $query): bool
                    => preg_match('#^/works/list/(?:release|reserve)/?$#', $path) === 1,
            ),
        ];
    }
}
