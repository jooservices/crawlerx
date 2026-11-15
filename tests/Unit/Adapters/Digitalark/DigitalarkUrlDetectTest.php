<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Digitalark;

use JOOservices\CrawlerX\Adapters\Digitalark\DigitalarkCrawler;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class DigitalarkUrlDetectTest extends TestCase
{
    private const BASE = 'https://shop.digital-ark.com';

    public function test_listing_urls_resolve_to_the_listing_route(): void
    {
        $crawler = $this->crawler();

        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/view/category/release-date')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/view/category/release-date?page=2')->crawlType);
    }

    public function test_item_urls_resolve_to_the_detail_route_with_or_without_tracking_query(): void
    {
        $crawler = $this->crawler();

        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/view/item/000000002230')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/view/item/000000002262?category_page_id=release-date')->crawlType);
    }

    public function test_foreign_and_non_catalogue_urls_do_not_match(): void
    {
        $crawler = $this->crawler();

        self::assertFalse($crawler->detectUrl('https://www.digital-ark.com/view/item/000000002230')->matched);
        self::assertFalse($crawler->detectUrl('https://gravidia.jp/view/item/000000002230')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/view/cart')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/view/category/')->matched);
        self::assertSame('digitalark', $crawler->name());
    }

    private function crawler(): DigitalarkCrawler
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('digitalark');
        self::assertInstanceOf(DigitalarkCrawler::class, $crawler);

        return $crawler;
    }
}
