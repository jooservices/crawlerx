<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Contracts\GalleryCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Tests\TestCase;

final class AdapterCapabilityTest extends TestCase
{
    public function test_performer_only_adapters_do_not_expose_movie_capabilities(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(manifests: $manifests);
        $registry->registerFromManifests($manifests);

        foreach (['javdatabase', 'warashi'] as $slug) {
            $adapter = $registry->resolve($slug);

            self::assertInstanceOf(PerformerListingCapable::class, $adapter);
            self::assertInstanceOf(PerformerDetailCapable::class, $adapter);
            self::assertNotInstanceOf(ListingCapable::class, $adapter);
            self::assertNotInstanceOf(DetailCapable::class, $adapter);
        }
    }

    public function test_gallery_only_adapters_do_not_expose_movie_or_performer_capabilities(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(manifests: $manifests);
        $registry->registerFromManifests($manifests);

        $adapter = $registry->resolve('eporner');

        self::assertInstanceOf(GalleryCapable::class, $adapter);
        self::assertSame('eporner', $adapter->name());
        self::assertNotInstanceOf(ListingCapable::class, $adapter);
        self::assertNotInstanceOf(DetailCapable::class, $adapter);
        self::assertNotInstanceOf(PerformerListingCapable::class, $adapter);
        self::assertNotInstanceOf(PerformerDetailCapable::class, $adapter);
    }
}
