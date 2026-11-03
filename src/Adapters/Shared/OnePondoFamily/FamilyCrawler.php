<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily;

use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\TypeInterface;

abstract class FamilyCrawler extends SimpleHtmlCrawler
{
    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    protected array $options = [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'application/json,text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,ja;q=0.8',
        ],
    ];

    abstract protected function siteConfig(): SiteConfig;

    protected function makeType(string $typeClass): TypeInterface
    {
        $config = $this->siteConfig();

        return new $typeClass($this->client, new UrlNormalizer($config), new MovieRowFields($config), $config);
    }
}
