<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fc2;

use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;

final class Fc2Crawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://adult.contents.fc2.com'];

    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            slug: 'fc2',
            label: 'FC2 Content Market',
            baseUrl: 'https://adult.contents.fc2.com',
            hosts: ['adult.contents.fc2.com'],
            detailPathPattern: '#^/article/(\d+)/?#i',
            listingUrl: 'https://adult.contents.fc2.com/',
            listingItemSelector: '.c-neoItem-1000_wrap, .c-neoItem-1000L_wrap',
            listingLinkSelector: 'a[href^="/article/"]',
            titleSuffixes: [' | FC2コンテンツマーケット'],
            tagSelectors: ['a[href*="/tags/"]', 'a[href*="/search/"]'],
            screenshotSelectors: ['a[href*="storage"]'],
            externalIdPrefix: 'FC2-PPV-',
        );
    }
}
