<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Cache;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Fetch\Cache\ResponseCache;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Http\SeededCrawlHttpClient;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\Support\ExpiringArrayCache;
use PHPUnit\Framework\TestCase;

final class LongCaptchaChallengeCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_shared_response_cache_does_not_store_a_long_captcha_challenge(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/long-captcha';
        $body = $this->longCaptchaBody();
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], $body))
            ->push(TestResponse::make(200, [], $body)));
        $handler = new ResponseCache(
            new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore()),
            new ExpiringArrayCache(static fn(): float => 1.0),
            60,
        );

        $handler->fetch($url, $this->profile(), FetchMethod::Http);
        $handler->fetch($url, $this->profile(), FetchMethod::Http);

        self::assertCount(2, ClientBuilder::recorded());
    }

    public function test_request_scoped_memo_does_not_store_a_long_captcha_seed(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/long-captcha';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(503, [], 'fresh first response'))
            ->push(TestResponse::make(503, [], 'fresh second response')));
        $seed = new FetchResultDto(
            ok: true,
            body: $this->longCaptchaBody(),
            status: 200,
            methodUsed: FetchMethod::Playwright,
            elapsedMs: 1,
            challengeDetected: false,
            finalUrl: $url,
        );
        $client = new SeededCrawlHttpClient((new ClientFactory())->factory(), $seed);

        $first = $client->get($url)->toPsrResponse();
        $second = $client->get($url)->toPsrResponse();

        self::assertSame('fresh first response', (string) $first->getBody());
        self::assertSame('fresh second response', (string) $second->getBody());
        self::assertCount(2, ClientBuilder::recorded());
    }

    private function longCaptchaBody(): string
    {
        return '<html><body><form><div class="g-recaptcha" data-sitekey="example"></div></form>'
            . str_repeat(' ', 20000)
            . '</body></html>';
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'test',
            displayName: 'Test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: ['Accept' => 'text/html']),
        );
    }
}
