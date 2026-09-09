<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Services\UrlClassifier;
use JOOservices\CrawlerX\Tests\Support\ManifestFixtureCatalog;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class UrlDetectTest extends TestCase
{
    /**
     * @return iterable<string, array{string, FixtureSampleDto}>
     */
    public static function manifestSamplesProvider(): iterable
    {
        foreach (ManifestFixtureCatalog::executableSamples() as $entry) {
            $key = $entry['slug'] . ':' . $entry['sample']->type . ':' . $entry['sample']->name;

            yield $key => [$entry['slug'], $entry['sample']];
        }
    }

    #[DataProvider('manifestSamplesProvider')]
    public function test_adapter_detect_url_matches_manifest_sample(string $slug, FixtureSampleDto $sample): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);

        $adapter = $registry->resolve($slug);
        self::assertInstanceOf(UrlDetectCapable::class, $adapter);

        $result = $adapter->detectUrl($sample->url);

        self::assertTrue($result->hostMatched);
        self::assertTrue($result->matched);
        self::assertInstanceOf(CrawlType::class, $result->crawlType);
    }

    public function test_url_classifier_resolves_detectable_manifest_samples(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $classifier = new UrlClassifier($registry);

        foreach (ManifestFixtureCatalog::executableSamples() as $entry) {
            $adapter = $registry->resolve($entry['slug']);
            if (! $adapter instanceof UrlDetectCapable) {
                continue;
            }

            $detected = $adapter->detectUrl($entry['sample']->url);
            if (! $detected->matched || ! $detected->crawlType instanceof CrawlType) {
                continue;
            }

            $classified = $classifier->classify($entry['sample']->url);

            self::assertSame($entry['slug'], $classified['slug']);
            self::assertSame($detected->crawlType, $classified['type']);
        }
    }

    public function test_url_classifier_honors_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $classifier = new UrlClassifier($registry);

        $classified = $classifier->classify(
            'https://onejav.com/torrent/ymds282',
            siteOverride: 'onejav',
            typeOverride: CrawlType::Detail,
            pageOverride: 3,
        );

        self::assertSame('onejav', $classified['slug']);
        self::assertSame(CrawlType::Detail, $classified['type']);
        self::assertSame(3, $classified['page']);
    }
}
