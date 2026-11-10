<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\CleansText;
use PHPUnit\Framework\TestCase;

final class CleansTextTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use CleansText;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_clean_text(): void
    {
        self::assertSame('A & B c', $this->helper->call('cleanText', "A &amp;\n B\t c "));
        self::assertSame('A & B', $this->helper->call('cleanTextWithoutTrailingPhrase', "A &amp; B  All Titles Information ", 'All Titles Information'));
        self::assertSame('A All Titles Information B', $this->helper->call('cleanTextWithoutTrailingPhrase', 'A All Titles Information B', 'All Titles Information'));
    }
}
