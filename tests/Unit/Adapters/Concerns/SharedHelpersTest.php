<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\CoercesValues;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;
use JOOservices\CrawlerX\Adapters\Concerns\ResolvesLinkUrls;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

final class SharedHelpersTest extends TestCase
{
    private object $helper;

    protected function setUp(): void
    {
        $this->helper = new class {
            use CoercesValues;
            use NormalizesUrls;
            use ParsesHtml;
            use ParsesUrlParts;
            use ResolvesLinkUrls;

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
        self::assertSame(1, $this->helper->call('digitPage', '0'));
        self::assertSame(3, $this->helper->call('digitPage', '3'));
        self::assertNull($this->helper->call('digitPage', '3x'));
        self::assertNull($this->helper->call('digitPage', null));
        self::assertSame('2026-01-02', $this->helper->call('dateString', '2026-01-02'));
        self::assertNull($this->helper->call('dateString', '  '));
        self::assertNull($this->helper->call('dateString', null));
        self::assertSame(['a' => 1], $this->helper->call('arrayValue', ['nested' => ['a' => 1]], 'nested'));
        self::assertSame([], $this->helper->call('arrayValue', ['x' => 'y'], 'missing'));
        self::assertSame([], $this->helper->call('arrayValue', ['x' => 'y'], 'x'));
    }

    public function test_link_url(): void
    {
        $crawler = new Crawler('<html><body><a class="next" href="/page/2">next</a></body></html>', 'https://a.test/list');

        self::assertSame('https://a.test/page/2', $this->helper->call('linkUrl', $crawler, 'a.next', 'https://a.test/list'));
        self::assertNull($this->helper->call('linkUrl', $crawler, 'a.missing', 'https://a.test/list'));
    }
}
