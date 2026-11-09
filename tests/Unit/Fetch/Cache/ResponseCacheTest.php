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
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\Support\ExpiringArrayCache;
use PHPUnit\Framework\TestCase;

final class ResponseCacheTest extends TestCase
{
    private ?string $originalResponseCacheTtl = null;

    protected function setUp(): void
    {
        parent::setUp();
        $ttl = getenv('CRAWLERX_RESPONSE_CACHE_TTL');
        $this->originalResponseCacheTtl = is_string($ttl) ? $ttl : null;
    }

    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        if ($this->originalResponseCacheTtl === null) {
            putenv('CRAWLERX_RESPONSE_CACHE_TTL');
        } else {
            putenv('CRAWLERX_RESPONSE_CACHE_TTL=' . $this->originalResponseCacheTtl);
        }
        parent::tearDown();
    }

    public function test_cache_miss_fetches_and_the_following_hit_reuses_the_response(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/movie/1';
        $this->respondTwice($url, 200, '<html><body>usable response body for cache test</body></html>');
        $handler = $this->cache(new ExpiringArrayCache(static fn(): float => 1.0), 60);

        $miss = $handler->fetch($url, $this->profile(), FetchMethod::Http);
        $hit = $handler->fetch($url, $this->profile(), FetchMethod::Http);

        self::assertTrue($miss->ok);
        self::assertTrue($hit->ok);
        self::assertSame($miss->body, $hit->body);
        self::assertSame(0, $hit->elapsedMs);
        self::assertCount(1, ClientBuilder::recorded());
    }

    public function test_cache_key_includes_url_and_request_headers(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/movie/1';
        $otherUrl = 'https://example.test/movie/2';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], '<html><body>english response body for cache</body></html>'))
            ->push(TestResponse::make(200, [], '<html><body>japanese response body for cache</body></html>')));
        ClientBuilder::respond('GET', $otherUrl, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], '<html><body>another URL response body for cache</body></html>')));
        $handler = $this->cache(new ExpiringArrayCache(static fn(): float => 1.0), 60);

        $english = $handler->fetch($url, $this->profile(), FetchMethod::Http, new CrawlOptionsDto(
            http: new HttpOptionsDto(headers: ['Accept-Language' => 'en']),
        ));
        $japanese = $handler->fetch($url, $this->profile(), FetchMethod::Http, new CrawlOptionsDto(
            http: new HttpOptionsDto(headers: ['Accept-Language' => 'ja']),
        ));
        $differentUrl = $handler->fetch($otherUrl, $this->profile(), FetchMethod::Http);

        self::assertNotSame($english->body, $japanese->body);
        self::assertNotSame($english->body, $differentUrl->body);
        self::assertCount(3, ClientBuilder::recorded());
    }

    public function test_entries_expire_after_the_configured_ttl(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/movie/ttl';
        $this->respondTwice($url, 200, '<html><body>response body with configured cache ttl</body></html>');
        $now = 10.0;
        $cache = new ExpiringArrayCache(static function () use (&$now): float {
            return $now;
        });
        $handler = $this->cache($cache, 5);

        $handler->fetch($url, $this->profile(), FetchMethod::Http);
        $now = 14.0;
        $handler->fetch($url, $this->profile(), FetchMethod::Http);
        self::assertCount(1, ClientBuilder::recorded());

        $now = 15.0;
        $handler->fetch($url, $this->profile(), FetchMethod::Http);

        self::assertCount(2, ClientBuilder::recorded());
    }

    public function test_non_2xx_challenge_and_captcha_responses_are_not_cached(): void
    {
        ClientBuilder::fake();
        $cases = [
            ['/not-found', 404, '<html><body>not found response body with enough content</body></html>'],
            ['/server-error', 503, '<html><body>server error response body with enough content</body></html>'],
            ['/challenge', 200, '<html><title>Just a moment...</title><body>challenge page response</body></html>'],
            ['/captcha', 200, '<html><body><form class="captcha">captcha response body</form></body></html>'],
            ['/hcaptcha', 200, '<html><body><script src="https://hcaptcha.com/api.js"></script>verify</body></html>'],
        ];
        foreach ($cases as [$path, $status, $body]) {
            $this->respondTwice('https://example.test' . $path, $status, $body);
        }
        $handler = $this->cache(new ExpiringArrayCache(static fn(): float => 1.0), 60);

        foreach ($cases as [$path]) {
            $url = 'https://example.test' . $path;
            $handler->fetch($url, $this->profile(), FetchMethod::Http);
            $handler->fetch($url, $this->profile(), FetchMethod::Http);
        }

        self::assertCount(10, ClientBuilder::recorded());
    }

    public function test_cache_hit_skips_host_throttle_wait(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/movie/throttled';
        $this->respondTwice($url, 200, '<html><body>usable response body for throttle cache</body></html>');
        $now = 0.0;
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $this->cache(new ExpiringArrayCache(static fn(): float => 1.0), 60)],
            hostThrottle: new HostThrottle(
                clock: static function () use (&$now): float {
                    return $now;
                },
                sleeper: static function (int $microseconds) use (&$now): void {
                    $now += $microseconds / 1_000_000;
                },
            ),
        );

        $chain->fetch($url, $this->profile(withThrottle: true), [FetchMethod::Http]);
        $chain->fetch($url, $this->profile(withThrottle: true), [FetchMethod::Http]);

        self::assertSame(0.0, $now);
        self::assertCount(1, ClientBuilder::recorded());
    }

    public function test_response_cache_ttl_is_read_from_environment_and_defaults_off(): void
    {
        putenv('CRAWLERX_RESPONSE_CACHE_TTL');
        self::assertSame(0, FetchRuntimeConfig::fromEnvironment()->responseCacheTtlSeconds);

        putenv('CRAWLERX_RESPONSE_CACHE_TTL=30');
        self::assertSame(30, FetchRuntimeConfig::fromEnvironment()->responseCacheTtlSeconds);

        putenv('CRAWLERX_RESPONSE_CACHE_TTL=-1');
        self::assertSame(0, FetchRuntimeConfig::fromEnvironment()->responseCacheTtlSeconds);
    }

    public function test_zero_ttl_disables_the_response_cache(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/movie/cache-disabled';
        $this->respondTwice($url, 200, '<html><body>usable response body with cache disabled</body></html>');
        $handler = $this->cache(new ExpiringArrayCache(static fn(): float => 1.0), 0);

        $handler->fetch($url, $this->profile(), FetchMethod::Http);
        $handler->fetch($url, $this->profile(), FetchMethod::Http);

        self::assertCount(2, ClientBuilder::recorded());
    }

    private function cache(ExpiringArrayCache $cache, int $ttl): ResponseCache
    {
        return new ResponseCache(
            new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore()),
            $cache,
            $ttl,
        );
    }

    private function profile(bool $withThrottle = false): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'test',
            displayName: 'Test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: ['Accept' => 'text/html']),
            defaultThrottle: $withThrottle
                ? ['default_gap_seconds' => 10.0, 'min_gap_seconds' => 10.0, 'max_gap_seconds' => 10.0]
                : null,
        );
    }

    private function respondTwice(string $url, int $status, string $body): void
    {
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make($status, [], $body))
            ->push(TestResponse::make($status, [], $body)));
    }
}
