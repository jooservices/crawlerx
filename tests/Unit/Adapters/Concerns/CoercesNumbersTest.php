<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\CoercesNumbers;
use PHPUnit\Framework\TestCase;

final class CoercesNumbersTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use CoercesNumbers;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_positive_int_and_page_number_from_text(): void
    {
        self::assertSame(5, $this->helper->call('positiveInt', '5'));
        self::assertNull($this->helper->call('positiveInt', 0));
        self::assertNull($this->helper->call('positiveInt', 'x'));
        self::assertSame(1, $this->helper->call('pageNumberFromText', '0'));
        self::assertSame(7, $this->helper->call('pageNumberFromText', '7'));
        self::assertNull($this->helper->call('pageNumberFromText', ' 7'));
        self::assertNull($this->helper->call('pageNumberFromText', null));
    }

    public function test_integer_stat(): void
    {
        self::assertSame(1234, $this->helper->call('integerStat', '1,234'));
        self::assertSame(1234, $this->helper->call('integerStat', '1 234'));
        self::assertSame(0, $this->helper->call('integerStat', 'n/a'));
    }
}
