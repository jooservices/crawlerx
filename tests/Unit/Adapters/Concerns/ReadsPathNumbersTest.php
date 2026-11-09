<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\ReadsPathNumbers;
use PHPUnit\Framework\TestCase;

final class ReadsPathNumbersTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use ReadsPathNumbers;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_path_number_and_integer(): void
    {
        self::assertSame(3, $this->helper->call('pathNumber', 'https://a.test/list/page/3/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathNumber', 'https://a.test/list/page/0/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathNumber', 'https://a.test/list', '#/page/(\d+)/?#'));
        self::assertSame(0, $this->helper->call('pathInteger', 'https://a.test/list/page/0/', '#/page/(\d+)/?#'));
        self::assertSame(3, $this->helper->call('pathInteger', 'https://a.test/list/page/3/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathInteger', 'https://a.test/list', '#/page/(\d+)/?#'));
    }
}
