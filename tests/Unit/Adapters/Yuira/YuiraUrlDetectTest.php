<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Yuira;

use JOOservices\CrawlerX\Adapters\Yuira\YuiraCrawler;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class YuiraUrlDetectTest extends TestCase
{
    private const BASE = 'https://yuira.jp';

    public function test_url_detection_matches_only_the_product_listing_and_product_detail_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('yuira');

        self::assertInstanceOf(YuiraCrawler::class, $crawler);
        self::assertSame('yuira', $crawler->name());
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/allproduct/')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/allproduct/?orderby=date&product-page=2')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/product/%e3%81%ab%e3%81%98%e3%82%ab%e3%83%8e/')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/product/gods585/')->crawlType);
        self::assertFalse($crawler->detectUrl(self::BASE . '/product-category/goods/')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/product/')->matched);
        self::assertFalse($crawler->detectUrl('https://example.com/product/gods585/')->matched);
    }
}
