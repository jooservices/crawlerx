<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TokyoHot;

use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;

final class TokyoHotCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://my.tokyo-hot.com'];

    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            slug: 'tokyohot',
            label: 'Tokyo-Hot',
            baseUrl: 'https://my.tokyo-hot.com',
            hosts: ['my.tokyo-hot.com', 'tokyo-hot.com'],
            detailPathPattern: '#^/product/([^/]+)/?$#i',
            listingUrl: 'https://my.tokyo-hot.com/index?lang=en',
            listingItemSelector: '.new li, .grid li',
            listingLinkSelector: 'a[href^="/product/"]',
            titleSuffixes: [' | Tokyo-Hot 東京熱'],
            performerSelectors: ['dl.info a[href*="cast"]'],
            tagSelectors: ['dl.info a[href*="type=play"]'],
            screenshotSelectors: ['.scap a[href]'],
        );
    }
}
