<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Onejav\OnejavCrawler;
use JOOservices\CrawlerX\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Tests\TestCase;
use stdClass;

final class RegistryTest extends TestCase
{
    public function test_register_and_all_returns_map(): void
    {
        $registry = new AdapterRegistry();
        $registry->register('onejav', stdClass::class);

        self::assertSame(['onejav' => stdClass::class], $registry->all());
    }

    public function test_resolve_throws_when_name_not_registered(): void
    {
        $registry = new AdapterRegistry();

        $this->expectException(AdapterNotFoundException::class);
        $this->expectExceptionMessage('unknown');

        $registry->resolve('unknown');
    }

    public function test_register_overwrites_existing_name(): void
    {
        $registry = new AdapterRegistry();
        $registry->register('site', stdClass::class);
        $registry->register('site', stdClass::class);

        self::assertCount(1, $registry->all());
    }

    public function test_resolve_can_instantiate_registered_adapter(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(manifests: $manifests);
        $registry->register('onejav', OnejavCrawler::class);

        self::assertInstanceOf(OnejavCrawler::class, $registry->resolve('onejav'));
    }
}
