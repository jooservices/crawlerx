<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Duga;

use JOOservices\CrawlerX\Adapters\Duga\Types\Detail;
use JOOservices\CrawlerX\Adapters\Duga\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class DugaCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://duga.jp'];

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
            slug: 'duga',
            label: 'DUGA',
            baseUrl: 'https://duga.jp',
            hosts: ['duga.jp'],
            detailPathPattern: '#^/ppv/([^/]+)/?$#i',
            listingUrl: 'https://duga.jp/ranking/',
            listingItemSelector: '.sidemenu, .listitem, .contentslist:not(.empty)',
            listingLinkSelector: 'a[href^="/ppv/"]',
            titleSuffixes: [' | アダルト動画 DUGA'],
            performerSelectors: ['a[href*="performer"]', 'a[href*="actress"]'],
            tagSelectors: ['a[href*="category"]'],
            screenshotSelectors: ['a[href*="scene"]'],
        );
    }
}
