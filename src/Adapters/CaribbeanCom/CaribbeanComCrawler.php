<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom;

use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;

final class CaribbeanComCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://en.caribbeancom.com'];

    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            slug: 'caribbeancom',
            label: 'Caribbeancom',
            baseUrl: 'https://en.caribbeancom.com',
            hosts: ['en.caribbeancom.com', 'caribbeancom.com'],
            detailPathPattern: '#^/eng/moviepages/([^/]+)/index\.html$#i',
            listingUrl: 'https://en.caribbeancom.com/eng/index2.htm',
            listingItemSelector: '.entry, .swiper-slide',
            listingLinkSelector: 'a[href*="/eng/moviepages/"]',
            titleSuffixes: [' - Caribbeancom.com'],
            performerSelectors: ['a[href*="actress"]', 'a[href*="girls"]'],
            tagSelectors: ['a[href*="genre"]'],
            screenshotSelectors: ['a[href*="/images/"]'],
        );
    }
}
