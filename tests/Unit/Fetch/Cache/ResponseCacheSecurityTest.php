<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Cache;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Cache\ResponseCache;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\Support\ExpiringArrayCache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class ResponseCacheSecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_requests_with_cookie_or_authorization_headers_bypass_the_shared_cache(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/private';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], '<html><body>private response body for cache</body></html>'))
            ->push(TestResponse::make(200, [], '<html><body>private response body for cache</body></html>'))
            ->push(TestResponse::make(200, [], '<html><body>private response body for cache</body></html>'))
            ->push(TestResponse::make(200, [], '<html><body>private response body for cache</body></html>')));
        $cache = new ExpiringArrayCache(static fn(): float => 1.0);
        $handler = $this->handler($cache);

        $handler->fetch($url, $this->profile(['Accept' => 'text/html', 'Cookie' => 'session=secret']), FetchMethod::Http);
        $handler->fetch($url, $this->profile(['Accept' => 'text/html', 'Cookie' => 'session=secret']), FetchMethod::Http);
        $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http, new CrawlOptionsDto(
            http: new HttpOptionsDto(headers: ['Authorization' => 'Bearer secret']),
        ));
        $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http, new CrawlOptionsDto(
            http: new HttpOptionsDto(headers: ['Authorization' => 'Bearer secret']),
        ));

        self::assertCount(4, ClientBuilder::recorded());
        self::assertSame([], $this->cacheItems($cache));
    }

    public function test_set_cookie_and_private_responses_are_not_cached_between_sessions(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/session';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, ['Set-Cookie' => 'session=first'], '<html><body>first usable response</body></html>'))
            ->push(TestResponse::make(200, ['Set-Cookie' => 'session=second'], '<html><body>second usable response</body></html>')));
        $handler = $this->handler(new ExpiringArrayCache(static fn(): float => 1.0));

        $first = $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);
        $second = $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);

        self::assertSame(['session=first'], $first->headers['Set-Cookie']);
        self::assertSame(['session=second'], $second->headers['Set-Cookie']);
        self::assertCount(2, ClientBuilder::recorded());
    }

    public function test_proxy_backed_requests_bypass_the_response_cache(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/geo-specific';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], '<html><body>first region response body</body></html>'))
            ->push(TestResponse::make(200, [], '<html><body>second region response body</body></html>')));
        $cache = new ExpiringArrayCache(static fn(): float => 1.0);
        $handler = $this->handler($cache);

        $first = $handler->fetch($url, $this->profile(['Accept' => 'text/html'], 'http://proxy-user:proxy-secret@proxy.example:8080'), FetchMethod::Http);
        $second = $handler->fetch($url, $this->profile(['Accept' => 'text/html'], 'http://proxy-user:proxy-secret@proxy.example:8080'), FetchMethod::Http);

        self::assertNotSame($first->body, $second->body);
        self::assertCount(2, ClientBuilder::recorded());
        self::assertSame([], $this->cacheItems($cache));
    }

    public function test_credentials_in_query_parameters_bypass_the_response_cache(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/private?access_key=query-secret';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], '<html><body>first private query response</body></html>'))
            ->push(TestResponse::make(200, [], '<html><body>second private query response</body></html>')));
        $cache = new ExpiringArrayCache(static fn(): float => 1.0);
        $handler = $this->handler($cache);

        $first = $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);
        $second = $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);

        self::assertNotSame($first->body, $second->body);
        self::assertCount(2, ClientBuilder::recorded());
        self::assertSame([], $this->cacheItems($cache));
    }

    public function test_private_vary_and_credential_response_headers_are_not_cached(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/private-response';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, ['Cache-Control' => 'private'], '<html><body>private response body one</body></html>'))
            ->push(TestResponse::make(200, ['Vary' => 'Accept, *'], '<html><body>vary response body two</body></html>'))
            ->push(TestResponse::make(200, ['X-Access-Key' => 'response-secret'], '<html><body>keyed response body three</body></html>')));
        $cache = new ExpiringArrayCache(static fn(): float => 1.0);
        $handler = $this->handler($cache);

        $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);
        $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);
        $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);

        self::assertCount(3, ClientBuilder::recorded());
        self::assertSame([], $this->cacheItems($cache));
    }

    public function test_cache_entries_are_json_strings_and_malformed_values_are_ignored(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/cache-format';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())->push(TestResponse::make(
            200,
            [],
            '<html><body>valid cached response body</body></html>',
        )));
        $cache = new ExpiringArrayCache(static fn(): float => 1.0);
        $handler = $this->handler($cache);

        $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);

        $items = $this->cacheItems($cache);
        self::assertCount(1, $items);
        $item = reset($items);
        self::assertIsArray($item);
        self::assertIsString($item['value'] ?? null);
        self::assertIsArray(json_decode($item['value'], true));
    }

    public function test_non_string_cache_entries_are_ignored(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/malformed-cache';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())->push(TestResponse::make(
            200,
            [],
            '<html><body>fresh response after malformed cache</body></html>',
        )));
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn(new \stdClass());
        $cache->method('set')->willReturn(true);
        $handler = new ResponseCache(
            new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore()),
            $cache,
            60,
        );

        $result = $handler->fetch($url, $this->profile(['Accept' => 'text/html']), FetchMethod::Http);

        self::assertTrue($result->ok);
        self::assertSame('<html><body>fresh response after malformed cache</body></html>', $result->body);
        self::assertCount(1, ClientBuilder::recorded());
    }

    private function handler(ExpiringArrayCache $cache): ResponseCache
    {
        return new ResponseCache(new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore()), $cache, 60);
    }

    /** @param array<string, string> $headers */
    private function profile(array $headers, ?string $proxy = null): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'test',
            displayName: 'Test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: $headers, proxy: $proxy),
        );
    }

    /** @return array<string, array{value: mixed, expiresAt: ?float}> */
    private function cacheItems(ExpiringArrayCache $cache): array
    {
        $property = new \ReflectionProperty(ExpiringArrayCache::class, 'items');

        /** @var array<string, array{value: mixed, expiresAt: ?float}> $items */
        $items = $property->getValue($cache);

        return $items;
    }
}
