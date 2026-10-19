<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Heyzo;

use JOOservices\CrawlerX\Adapters\Heyzo\Types\Detail;
use JOOservices\CrawlerX\Adapters\Heyzo\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class HeyzoCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://en.heyzo.com'];

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
            slug: 'heyzo',
            label: 'HEYZO',
            baseUrl: 'https://en.heyzo.com',
            hosts: ['en.heyzo.com', 'heyzo.com'],
            detailPathPattern: '#^/moviepages/(\d+)/index\.html$#i',
            listingUrl: 'https://en.heyzo.com/listpages/all_1.html',
            listingItemSelector: '.movie',
            listingLinkSelector: 'a[href*="/moviepages/"]',
            titleSuffixes: [' - HEYZO'],
            performerSelectors: ['a.actor', 'a[href*="actor"]'],
            tagSelectors: ['a[href*="genre"]', 'a[href*="tag"]'],
            screenshotSelectors: ['a[href*="gallery"]', 'a[rel="lightbox"]'],
            externalIdPrefix: 'HEYZO-',
        );
    }
}
