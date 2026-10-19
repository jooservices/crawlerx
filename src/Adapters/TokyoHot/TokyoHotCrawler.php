<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TokyoHot;

use JOOservices\CrawlerX\Adapters\TokyoHot\Types\Detail;
use JOOservices\CrawlerX\Adapters\TokyoHot\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\UrlDetectionResult;

final class TokyoHotCrawler extends AbstractCatalogCrawler
{
    protected array $options = ['base_uri' => 'https://my.tokyo-hot.com'];

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Detail($this->client))->execute($request);
    }

    /** The home page and the paged `/product/` list are both listings. */
    public function detectUrl(string $url): UrlDetectionResult
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        if (is_string($host) && in_array($host, $this->definition()->hosts, true) && in_array($path, ['/product', '/index'], true)) {
            $root = parent::detectUrl($this->definition()->baseUrl . '/');

            return new UrlDetectionResult(
                hostMatched: $root->hostMatched,
                matched: $root->matched,
                crawlType: $root->crawlType,
                entity: $root->entity,
                urlType: $root->urlType,
                normalizedUrl: $url,
            );
        }

        return parent::detectUrl($url);
    }

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
