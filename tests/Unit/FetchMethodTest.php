<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Enums\FetchMethod;
use PHPUnit\Framework\TestCase;

final class FetchMethodTest extends TestCase
{
    public function test_default_chains_preserve_cheap_then_browser_fallback_order(): void
    {
        self::assertSame([
            FetchMethod::Http,
            FetchMethod::CurlImpersonate,
            FetchMethod::Playwright,
            FetchMethod::PlaywrightStealth,
            FetchMethod::PuppeteerStealth,
            FetchMethod::Flaresolverr,
        ], FetchMethod::defaultChain());
        self::assertSame([
            FetchMethod::Playwright,
            FetchMethod::PlaywrightStealth,
            FetchMethod::PuppeteerStealth,
            FetchMethod::Flaresolverr,
        ], FetchMethod::browserChain());
        self::assertSame([FetchMethod::Http], FetchMethod::httpOnlyChain());
    }
}
