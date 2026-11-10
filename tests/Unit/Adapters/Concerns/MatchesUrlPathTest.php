<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\MatchesUrlPath;
use PHPUnit\Framework\TestCase;

final class MatchesUrlPathTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use MatchesUrlPath;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_path_match(): void
    {
        self::assertSame('42', $this->helper->call('pathMatch', 'https://a.test/actress/42/', '#/actress/(\d+)/?$#'));
        self::assertNull($this->helper->call('pathMatch', 'https://a.test/other', '#/actress/(\d+)#'));
    }
}
