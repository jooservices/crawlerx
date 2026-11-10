<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesDateStrings;
use PHPUnit\Framework\TestCase;

final class ParsesDateStringsTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use ParsesDateStrings;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_date_string(): void
    {
        self::assertSame('2026-01-02', $this->helper->call('dateString', '2026-01-02'));
        self::assertNull($this->helper->call('dateString', '  '));
        self::assertNull($this->helper->call('dateString', null));
    }
}
