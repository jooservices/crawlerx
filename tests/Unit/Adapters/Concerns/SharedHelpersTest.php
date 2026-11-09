<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Concerns;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ResolvesLinkUrls;
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
            use NormalizesUrls;
            use ParsesHtml;
            use ResolvesLinkUrls;

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function test_absolute_from_origin_preserves_adapter_url_semantics(): void
    {
        self::assertSame('https://a.test/image.jpg', $this->helper->call('absoluteFromOrigin', 'https://a.test/path/page', '/image.jpg'));
        self::assertSame('https://a.test/cdn/image.jpg', $this->helper->call('absoluteFromOrigin', 'https://a.test/path/page', '//cdn/image.jpg'));
        self::assertSame('https://other.test/image.jpg', $this->helper->call('absoluteFromOrigin', 'https://a.test/path/page', 'https://other.test/image.jpg'));
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

    private function response(int $statusCode, string $content): ResponseInterface
    {
        $body = $this->createStub(StreamInterface::class);
        $body->method('__toString')->willReturn($content);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($body);
        $response->method('getStatusCode')->willReturn($statusCode);

        return $response;
    }

    public function test_link_url(): void
    {
        $crawler = new Crawler('<html><body><a class="next" href="/page/2">next</a></body></html>', 'https://a.test/list');

        self::assertSame('https://a.test/page/2', $this->helper->call('linkUrl', $crawler, 'a.next', 'https://a.test/list'));
        self::assertNull($this->helper->call('linkUrl', $crawler, 'a.missing', 'https://a.test/list'));
    }
}
