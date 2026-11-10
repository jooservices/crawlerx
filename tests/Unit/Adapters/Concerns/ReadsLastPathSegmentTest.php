<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\ReadsLastPathSegment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReadsLastPathSegmentTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use ReadsLastPathSegment;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function segments(): iterable
    {
        yield 'plain' => ['https://a.test/video/abc-123', 'abc-123'];
        yield 'trailing slash' => ['https://a.test/video/abc-123/', 'abc-123'];
        yield 'root' => ['https://a.test/', null];
        yield 'no path' => ['https://a.test', null];
    }

    #[DataProvider('segments')]
    public function test_last_path_segment(string $url, ?string $expected): void
    {
        self::assertSame($expected, $this->helper->call('lastPathSegment', $url));
    }

    public function test_last_path_segment_can_preserve_an_empty_root_segment(): void
    {
        self::assertSame('', $this->helper->call('lastPathSegment', 'https://a.test/', true));
        self::assertNull($this->helper->call('lastPathSegment', 'https://a.test', true));
    }
}
