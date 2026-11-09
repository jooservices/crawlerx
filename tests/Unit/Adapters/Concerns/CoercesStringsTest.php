<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\CoercesStrings;
use PHPUnit\Framework\TestCase;

final class CoercesStringsTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use CoercesStrings;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_non_empty_string(): void
    {
        self::assertSame('a b', $this->helper->call('nonEmptyString', '  a b '));
        self::assertNull($this->helper->call('nonEmptyString', '   '));
        self::assertNull($this->helper->call('nonEmptyString', 5));
    }
}
