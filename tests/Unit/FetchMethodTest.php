<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Enums\FetchMethod;
use PHPUnit\Framework\TestCase;

final class FetchMethodTest extends TestCase
{
    public function test_default_chains_preserve_cheap_then_browser_fallback_order(): void
    {
        putenv('CRAWLERX_CURL_IMPERSONATE=');
        self::assertSame([
            FetchMethod::Http,
            FetchMethod::Playwright,
            FetchMethod::Flaresolverr,
        ], FetchMethod::defaultChain());
        self::assertSame([
            FetchMethod::Playwright,
            FetchMethod::Flaresolverr,
        ], FetchMethod::browserChain());
        self::assertSame([FetchMethod::Http], FetchMethod::httpOnlyChain());
    }
}
