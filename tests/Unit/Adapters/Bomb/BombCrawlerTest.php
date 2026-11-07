<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Bomb;

use JOOservices\CrawlerX\Adapters\Bomb\BombCrawler;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class BombCrawlerTest extends TestCase
{
    private function crawler(): BombCrawler
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('bomb');
        self::assertInstanceOf(BombCrawler::class, $crawler);

        return $crawler;
    }

    public function test_registry_resolves_the_adapter(): void
    {
        self::assertSame('bomb', $this->crawler()->name());
    }

    public function test_url_inference_without_overrides(): void
    {
        $crawler = $this->crawler();

        foreach ([
            'https://www.bomb.tv/plus/index.html' => CrawlType::Listing,
            'https://www.bomb.tv/plus/' => CrawlType::Listing,
            'https://www.bomb.tv/plus/idolindex/mita.html' => CrawlType::Gallery,
            'https://www.bomb.tv/plus/idolindex/chi-maki.html#chi-maki01' => CrawlType::Gallery,
        ] as $url => $type) {
            $result = $crawler->detectUrl($url);
            self::assertTrue($result->matched, $url);
            self::assertSame($type, $result->crawlType, $url);
        }

        self::assertFalse($crawler->detectUrl('https://www.bomb.tv/plus/sample/mita03.html')->matched);
    }
}
