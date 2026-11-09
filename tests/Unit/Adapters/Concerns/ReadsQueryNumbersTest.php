<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\ReadsQueryNumbers;
use PHPUnit\Framework\TestCase;

final class ReadsQueryNumbersTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use ReadsQueryNumbers;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_query_number(): void
    {
        self::assertSame(7, $this->helper->call('queryNumber', 'https://a.test/?page=7', 'page'));
        self::assertNull($this->helper->call('queryNumber', 'https://a.test/?page=0', 'page'));
        self::assertNull($this->helper->call('queryNumber', 'https://a.test/?page=x', 'page'));
    }

    public function test_numeric_query_numbers_and_page_numbers(): void
    {
        self::assertSame(12, $this->helper->call('positiveNumericQueryNumber', 'https://a.test/?page=12.9', 'page'));
        self::assertNull($this->helper->call('positiveNumericQueryNumber', 'https://a.test/?page=0', 'page'));
        self::assertSame(1, $this->helper->call('queryPageNumber', 'https://a.test/?page=0', 'page'));
        self::assertSame(12, $this->helper->call('queryPageNumber', 'https://a.test/?page=12.9', 'page'));
        self::assertNull($this->helper->call('queryPageNumber', 'https://a.test/?page=next', 'page'));
    }
}
