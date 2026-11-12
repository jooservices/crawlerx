<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Tma\TmaCrawler;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class TmaUrlDetectionTest extends TestCase
{
    private const BASE = 'https://www.tma.co.jp';

    public function test_url_detection_matches_the_product_listing_and_product_details_only(): void
    {
        $crawler = $this->tma();

        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/collections/tma')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/collections/tma?page=2')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/products/t38-073')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/collections/tma/products/fays-017')->crawlType);
    }

    public function test_url_detection_rejects_other_collections_and_foreign_hosts(): void
    {
        $crawler = $this->tma();
        self::assertSame('tma', $crawler->name());

        self::assertFalse($crawler->detectUrl(self::BASE . '/collections/tma-ranking')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/products/')->matched);
        self::assertFalse($crawler->detectUrl('https://www.hiyoko-av.com/products/t38-073')->matched);
    }

    private function tma(): TmaCrawler
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('tma');
        self::assertInstanceOf(TmaCrawler::class, $crawler);

        return $crawler;
    }
}
