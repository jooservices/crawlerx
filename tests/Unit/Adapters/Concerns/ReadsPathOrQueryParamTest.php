<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\ReadsPathOrQueryParam;
use PHPUnit\Framework\TestCase;

final class ReadsPathOrQueryParamTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use ReadsPathOrQueryParam;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_path_match_or_trimmed_query_parameter(): void
    {
        self::assertSame('42', $this->helper->call('pathMatchOrQueryParam', 'https://a.test/idol/detail/42', '#/idol/detail/([^/]+)#', 'id'));
        self::assertSame('abc', $this->helper->call('pathMatchOrQueryParam', 'https://a.test/idol?id=%20abc%20', '#/idol/detail/([^/]+)#', 'id'));
        self::assertNull($this->helper->call('pathMatchOrQueryParam', 'https://a.test/idol?id=%20%20', '#/idol/detail/([^/]+)#', 'id'));
    }
}
