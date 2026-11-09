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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
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

    public function test_absolute_from_origin_preserves_adapter_url_semantics(): void
    {
        self::assertSame('https://a.test/image.jpg', $this->helper->call('absoluteFromOrigin', 'https://a.test/path/page', '/image.jpg'));
        self::assertSame('https://a.test/cdn/image.jpg', $this->helper->call('absoluteFromOrigin', 'https://a.test/path/page', '//cdn/image.jpg'));
        self::assertSame('https://other.test/image.jpg', $this->helper->call('absoluteFromOrigin', 'https://a.test/path/page', 'https://other.test/image.jpg'));
    }

    public function test_last_path_segment_can_preserve_an_empty_root_segment(): void
    {
        self::assertSame('', $this->helper->call('lastPathSegment', 'https://a.test/', true));
        self::assertNull($this->helper->call('lastPathSegment', 'https://a.test', true));
    }

    public function test_path_match_and_number(): void
    {
        self::assertSame('42', $this->helper->call('pathMatch', 'https://a.test/actress/42/', '#/actress/(\d+)/?$#'));
        self::assertNull($this->helper->call('pathMatch', 'https://a.test/other', '#/actress/(\d+)#'));
        self::assertSame(3, $this->helper->call('pathNumber', 'https://a.test/list/page/3/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathNumber', 'https://a.test/list/page/0/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathNumber', 'https://a.test/list', '#/page/(\d+)/?#'));
        self::assertSame(0, $this->helper->call('pathInteger', 'https://a.test/list/page/0/', '#/page/(\d+)/?#'));
        self::assertSame(3, $this->helper->call('pathInteger', 'https://a.test/list/page/3/', '#/page/(\d+)/?#'));
        self::assertNull($this->helper->call('pathInteger', 'https://a.test/list', '#/page/(\d+)/?#'));
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

    public function test_numeric_query_numbers_and_page_numbers(): void
    {
        self::assertSame(12, $this->helper->call('positiveNumericQueryNumber', 'https://a.test/?page=12.9', 'page'));
        self::assertNull($this->helper->call('positiveNumericQueryNumber', 'https://a.test/?page=0', 'page'));
        self::assertSame(1, $this->helper->call('queryPageNumber', 'https://a.test/?page=0', 'page'));
        self::assertSame(12, $this->helper->call('queryPageNumber', 'https://a.test/?page=12.9', 'page'));
        self::assertNull($this->helper->call('queryPageNumber', 'https://a.test/?page=next', 'page'));
    }

    public function test_path_match_or_trimmed_query_parameter(): void
    {
        self::assertSame('42', $this->helper->call('pathMatchOrQueryParam', 'https://a.test/idol/detail/42', '#/idol/detail/([^/]+)#', 'id'));
        self::assertSame('abc', $this->helper->call('pathMatchOrQueryParam', 'https://a.test/idol?id=%20abc%20', '#/idol/detail/([^/]+)#', 'id'));
        self::assertNull($this->helper->call('pathMatchOrQueryParam', 'https://a.test/idol?id=%20%20', '#/idol/detail/([^/]+)#', 'id'));
    }

    public function test_has_query_param_requires_a_non_empty_scalar(): void
    {
        self::assertTrue($this->helper->call('hasQueryParam', 'https://a.test/?page=1', 'page'));
        self::assertFalse($this->helper->call('hasQueryParam', 'https://a.test/?page=%20%20', 'page'));
        self::assertFalse($this->helper->call('hasQueryParam', 'https://a.test/?page[]=1', 'page'));
        self::assertFalse($this->helper->call('hasQueryParam', 'https://a.test/', 'page'));
    }

    public function test_html_response_validation(): void
    {
        self::assertSame('<html>ok</html>', $this->helper->call('htmlResponse', $this->response(200, '<html>ok</html>'), 'site', 'detail'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('site detail page is blocked or unavailable.');
        $this->helper->call('htmlResponse', $this->response(503, 'maintenance'), 'site', 'detail');
    }

    public function test_html_response_rejects_challenges_and_empty_content(): void
    {
        try {
            $this->helper->call('htmlResponse', $this->response(200, 'Just a moment...'), 'site', 'listing');
            self::fail('Expected a challenge response to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('site listing page is blocked or unavailable.', $exception->getMessage());
        }

        try {
            $this->helper->call('htmlResponse', $this->response(200, '  '), 'site', 'listing');
            self::fail('Expected an empty response to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('site listing page is empty.', $exception->getMessage());
        }
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
        self::assertSame('A & B', $this->helper->call('cleanTextWithoutTrailingPhrase', "A &amp; B  All Titles Information ", 'All Titles Information'));
        self::assertSame('A All Titles Information B', $this->helper->call('cleanTextWithoutTrailingPhrase', 'A All Titles Information B', 'All Titles Information'));
        self::assertSame(1, $this->helper->call('pageNumberFromText', '0'));
        self::assertSame(7, $this->helper->call('pageNumberFromText', '7'));
        self::assertNull($this->helper->call('pageNumberFromText', ' 7'));
        self::assertNull($this->helper->call('pageNumberFromText', null));
        self::assertSame('2026-01-02', $this->helper->call('dateString', '2026-01-02'));
        self::assertNull($this->helper->call('dateString', '  '));
        self::assertNull($this->helper->call('dateString', null));
        self::assertSame(['a' => 1], $this->helper->call('arrayValue', ['nested' => ['a' => 1]], 'nested'));
        self::assertSame([], $this->helper->call('arrayValue', ['x' => 'y'], 'missing'));
        self::assertSame([], $this->helper->call('arrayValue', ['x' => 'y'], 'x'));
    }

    private function response(int $statusCode, string $content): ResponseInterface
    {
        $body = $this->createStub(StreamInterface::class);
        $body->method('__toString')->willReturn($content);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($body);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
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

    public function test_link_url(): void
    {
        $crawler = new Crawler('<html><body><a class="next" href="/page/2">next</a></body></html>', 'https://a.test/list');

        self::assertSame('https://a.test/page/2', $this->helper->call('linkUrl', $crawler, 'a.next', 'https://a.test/list'));
        self::assertNull($this->helper->call('linkUrl', $crawler, 'a.missing', 'https://a.test/list'));
    }
}
