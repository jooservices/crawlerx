<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Duga;

use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;

final class DugaCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://duga.jp'];

    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            slug: 'duga',
            label: 'DUGA',
            baseUrl: 'https://duga.jp',
            hosts: ['duga.jp'],
            detailPathPattern: '#^/ppv/([^/]+)/?$#i',
            listingUrl: 'https://duga.jp/main/',
            listingItemSelector: '.sidemenu, .listitem, .contentslist:not(.empty)',
            listingLinkSelector: 'a[href^="/ppv/"]',
            titleSuffixes: [' | アダルト動画 DUGA'],
            performerSelectors: ['a[href*="performer"]', 'a[href*="actress"]'],
            tagSelectors: ['a[href*="category"]'],
            screenshotSelectors: ['a[href*="scene"]'],
        );
    }
}
