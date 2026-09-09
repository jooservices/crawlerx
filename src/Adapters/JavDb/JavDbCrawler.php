<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDb;

use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;

final class JavDbCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://javdb.com'];

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
