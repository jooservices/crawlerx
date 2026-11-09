<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\StripsTitleCode;
use PHPUnit\Framework\TestCase;

final class StripsTitleCodeTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use StripsTitleCode;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_title_without_code(): void
    {
        self::assertSame('Title', $this->helper->call('titleWithoutCode', 'abc-123  Title', 'ABC-123'));
        self::assertSame('ABC-123', $this->helper->call('titleWithoutCode', 'ABC-123', 'ABC-123'));
        self::assertSame('Other', $this->helper->call('titleWithoutCode', 'Other', 'ABC-123'));
        self::assertNull($this->helper->call('titleWithoutCode', null, 'ABC-123'));
    }
}
