<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\ReadsQueryParams;
use PHPUnit\Framework\TestCase;

final class ReadsQueryParamsTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use ReadsQueryParams;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_query_param(): void
    {
        self::assertSame('7', $this->helper->call('queryParam', 'https://a.test/?page=7&x=1', 'page'));
        self::assertNull($this->helper->call('queryParam', 'https://a.test/?page=', 'page'));
        self::assertNull($this->helper->call('queryParam', 'https://a.test/?page[]=1', 'page'));
        self::assertNull($this->helper->call('queryParam', 'https://a.test/', 'page'));
    }

    public function test_has_query_param_requires_a_non_empty_scalar(): void
    {
        self::assertTrue($this->helper->call('hasQueryParam', 'https://a.test/?page=1', 'page'));
        self::assertFalse($this->helper->call('hasQueryParam', 'https://a.test/?page=%20%20', 'page'));
        self::assertFalse($this->helper->call('hasQueryParam', 'https://a.test/?page[]=1', 'page'));
        self::assertFalse($this->helper->call('hasQueryParam', 'https://a.test/', 'page'));
    }

    public function test_first_query_param_uses_the_first_present_alias(): void
    {
        self::assertSame('a1', $this->helper->call('firstQueryParam', 'https://a.test/?st=a1&s=b2', 'st', 's'));
        self::assertSame('b2', $this->helper->call('firstQueryParam', 'https://a.test/?s=%20b2%20', 'st', 's'));
        self::assertNull($this->helper->call('firstQueryParam', 'https://a.test/?st=&s=b2', 'st', 's'));
        self::assertNull($this->helper->call('firstQueryParam', 'https://a.test/?st[]=1', 'st', 's'));
        self::assertNull($this->helper->call('firstQueryParam', 'https://a.test/', 'st', 's'));
    }
}
