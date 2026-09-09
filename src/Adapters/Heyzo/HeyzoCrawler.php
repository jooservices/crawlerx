<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Heyzo;

use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;

final class HeyzoCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://en.heyzo.com'];

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
        );
    }
}
