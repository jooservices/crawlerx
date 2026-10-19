<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDb;

use JOOservices\CrawlerX\Adapters\JavDb\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavDb\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class JavDbCrawler extends AbstractCatalogCrawler
{
    // JavDB answers in the viewer's language (zh-TW without a language header); English gives stable labels and tag names.
    protected array $options = [
        'base_uri' => 'https://javdb.com',
        'headers' => ['Accept-Language' => 'en-US,en;q=0.9'],
    ];

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Detail($this->client))->execute($request);
    }

    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            slug: 'javdb',
            label: 'JavDB',
            baseUrl: 'https://javdb.com',
            hosts: ['javdb.com'],
            detailPathPattern: '#^/v/([^/]+)/?$#i',
            listingUrl: 'https://javdb.com/',
            listingItemSelector: '.movie-list .item',
            listingLinkSelector: 'a[href^="/v/"]',
            titleSuffixes: [' | JavDB 成人影片數據庫', ' | JavDB'],
            performerSelectors: ['a[href*="/actors/"]'],
            tagSelectors: ['a[href*="/tags?"]'],
            screenshotSelectors: ['.tile-images a[href]'],
        );
    }
}
