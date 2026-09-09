<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\Catalog;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Dto\UrlDetectionResult;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

abstract class AbstractCatalogCrawler extends AbstractBaseCrawler implements UrlDetectCapable
{
    use DetectsUrls {
        detectUrl as private detectCatalogUrl;
    }

    abstract protected function definition(): CatalogDefinition;

    public function name(): string
    {
        return $this->definition()->slug;
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client, $this->definition()))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Detail($this->client, $this->definition()))->execute($request);
    }

    public function detectUrl(string $url): UrlDetectionResult
    {
        $result = $this->detectCatalogUrl($url);
        $path = parse_url($url, PHP_URL_PATH);
        if (! $result->matched || ($path !== null && $path !== '' && $path !== '/')) {
            return $result;
        }

        return new UrlDetectionResult(
            hostMatched: $result->hostMatched,
            matched: true,
            crawlType: $result->crawlType,
            entity: $result->entity,
            urlType: $result->urlType,
            normalizedUrl: $this->definition()->listingUrl,
        );
    }

    /** @return list<string> */
    protected function urlDetectHostPatterns(): array
    {
        return $this->definition()->hosts;
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
                matcher: fn(string $url, string $path, string $query): bool => $this->definition()->isDetailPath($path),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 10,
                matcher: static fn(string $url, string $path, string $query): bool => true,
            ),
        ];
    }
}
