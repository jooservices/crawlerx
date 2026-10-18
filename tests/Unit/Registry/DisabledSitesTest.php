<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Registry;

use JOOservices\CrawlerX\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use PHPUnit\Framework\TestCase;

final class DisabledSitesTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('CRAWLERX_DISABLED_SITES');
    }

    public function test_no_site_is_disabled_by_default(): void
    {
        putenv('CRAWLERX_DISABLED_SITES');

        self::assertSame([], FileAdapterManifestRegistry::disabledSlugs());
        self::assertTrue((new FileAdapterManifestRegistry())->has('avfan'));
    }

    public function test_listed_sites_are_removed_from_the_registry(): void
    {
        putenv('CRAWLERX_DISABLED_SITES= AvFan , ,missav,AVFAN');

        $registry = new FileAdapterManifestRegistry();

        self::assertSame(['avfan', 'missav'], FileAdapterManifestRegistry::disabledSlugs());
        self::assertFalse($registry->has('avfan'));
        self::assertNull($registry->get('missav'));
        self::assertTrue($registry->has('avfan_profiles'));
        self::assertNotContains('avfan', $registry->slugs());
        self::assertArrayNotHasKey('avfan', $registry->adapterMap());
    }

    public function test_a_disabled_site_gets_a_clear_error_and_an_unknown_one_does_not(): void
    {
        putenv('CRAWLERX_DISABLED_SITES=avfan');

        self::assertSame('Adapter [avfan] is disabled by CRAWLERX_DISABLED_SITES.', (new AdapterNotFoundException('avfan'))->getMessage());
        self::assertSame('Adapter [nope] is not registered.', (new AdapterNotFoundException('nope'))->getMessage());
    }
}
