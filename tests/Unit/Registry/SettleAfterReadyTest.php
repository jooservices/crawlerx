<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Registry;

use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use PHPUnit\Framework\TestCase;

final class SettleAfterReadyTest extends TestCase
{
    public function test_the_manifest_value_reaches_the_playwright_profile(): void
    {
        $registry = new FileAdapterManifestRegistry();

        $javbus = $registry->get('javbus');
        self::assertNotNull($javbus);
        self::assertSame(4000, $javbus->settleAfterReadyMs);
        self::assertSame(4000, SiteProfileDto::fromManifest($javbus)->playwright?->settleAfterReadyMs);

        $onejav = $registry->get('onejav');
        self::assertNotNull($onejav);
        self::assertSame(0, $onejav->settleAfterReadyMs);
        self::assertSame(0, SiteProfileDto::fromManifest($onejav)->playwright?->settleAfterReadyMs);
    }
}
