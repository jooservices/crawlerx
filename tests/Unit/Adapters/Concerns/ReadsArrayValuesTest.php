<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\ReadsArrayValues;
use PHPUnit\Framework\TestCase;

final class ReadsArrayValuesTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use ReadsArrayValues;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_array_value(): void
    {
        self::assertSame(['a' => 1], $this->helper->call('arrayValue', ['nested' => ['a' => 1]], 'nested'));
        self::assertSame([], $this->helper->call('arrayValue', ['x' => 'y'], 'missing'));
        self::assertSame([], $this->helper->call('arrayValue', ['x' => 'y'], 'x'));
    }
}
