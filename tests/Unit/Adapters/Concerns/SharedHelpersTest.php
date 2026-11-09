<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\CoercesValues;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SharedHelpersTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use CoercesValues;
            use ParsesUrlParts;

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

    public function test_path_match_and_number(): void
    {
        self::assertSame('42', $this->helper->call('pathMatch', 'https://a.test/actress/42/', '#/actress/(\d+)/?$#'));
        self::assertNull($this->helper->call('pathMatch', 'https://a.test/other', '#/actress/(\d+)#'));
        self::assertSame(3, $this->helper->call('pathNumber', 'https://a.test/list/page/3/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathNumber', 'https://a.test/list/page/0/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathNumber', 'https://a.test/list', '#/page/(\d+)/?#'));
    }

    public function test_query_param_and_number(): void
    {
        self::assertSame('7', $this->helper->call('queryParam', 'https://a.test/?page=7&x=1', 'page'));
        self::assertNull($this->helper->call('queryParam', 'https://a.test/?page=', 'page'));
        self::assertNull($this->helper->call('queryParam', 'https://a.test/?page[]=1', 'page'));
        self::assertNull($this->helper->call('queryParam', 'https://a.test/', 'page'));
        self::assertSame(7, $this->helper->call('queryNumber', 'https://a.test/?page=7', 'page'));
        self::assertNull($this->helper->call('queryNumber', 'https://a.test/?page=0', 'page'));
        self::assertNull($this->helper->call('queryNumber', 'https://a.test/?page=x', 'page'));
    }

    public function test_value_coercion(): void
    {
        self::assertSame('a b', $this->helper->call('nonEmptyString', '  a b '));
        self::assertNull($this->helper->call('nonEmptyString', '   '));
        self::assertNull($this->helper->call('nonEmptyString', 5));
        self::assertSame(5, $this->helper->call('positiveInt', '5'));
        self::assertNull($this->helper->call('positiveInt', 0));
        self::assertNull($this->helper->call('positiveInt', 'x'));
        self::assertSame('A & B c', $this->helper->call('cleanText', "A &amp;\n B\t c "));
    }

    public function test_first_query_param_uses_the_first_present_alias(): void
    {
        self::assertSame('a1', $this->helper->call('firstQueryParam', 'https://a.test/?st=a1&s=b2', 'st', 's'));
        self::assertSame('b2', $this->helper->call('firstQueryParam', 'https://a.test/?s=%20b2%20', 'st', 's'));
        self::assertNull($this->helper->call('firstQueryParam', 'https://a.test/?st=&s=b2', 'st', 's'));
        self::assertNull($this->helper->call('firstQueryParam', 'https://a.test/?st[]=1', 'st', 's'));
        self::assertNull($this->helper->call('firstQueryParam', 'https://a.test/', 'st', 's'));
    }

    public function test_title_without_code(): void
    {
        self::assertSame('Title', $this->helper->call('titleWithoutCode', 'abc-123  Title', 'ABC-123'));
        self::assertSame('ABC-123', $this->helper->call('titleWithoutCode', 'ABC-123', 'ABC-123'));
        self::assertSame('Other', $this->helper->call('titleWithoutCode', 'Other', 'ABC-123'));
        self::assertNull($this->helper->call('titleWithoutCode', null, 'ABC-123'));
    }

    public function test_integer_stat(): void
    {
        self::assertSame(1234, $this->helper->call('integerStat', '1,234'));
        self::assertSame(1234, $this->helper->call('integerStat', '1 234'));
        self::assertSame(0, $this->helper->call('integerStat', 'n/a'));
    }
}
