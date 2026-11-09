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

final class CaptchaChallengeHeadingCacheRegressionTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_shared_response_cache_fetches_again_after_a_long_captcha_wall_with_a_heading(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/long-captcha';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], $this->challengeBody()))
            ->push(TestResponse::make(200, [], 'recovered page body')));
        $handler = new ResponseCache(
            new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore()),
            new ExpiringArrayCache(static fn(): float => 1.0),
            60,
        );

        $handler->fetch($url, $this->profile(), FetchMethod::Http);
        $recovered = $handler->fetch($url, $this->profile(), FetchMethod::Http);

        self::assertSame('recovered page body', $recovered->body);
        self::assertCount(2, ClientBuilder::recorded());
    }

    public function test_seeded_client_does_not_memoize_a_long_captcha_wall_with_a_heading(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/long-captcha';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], $this->challengeBody()))
            ->push(TestResponse::make(200, [], 'recovered page body')));
        $seed = new FetchResultDto(
            ok: true,
            body: $this->challengeBody(),
            status: 200,
            methodUsed: FetchMethod::Playwright,
            elapsedMs: 1,
            challengeDetected: false,
            finalUrl: $url,
        );
        $client = new SeededCrawlHttpClient((new ClientFactory())->factory(), $seed);

        $client->get($url);
        $recovered = $client->get($url)->toPsrResponse();

        self::assertSame('recovered page body', (string) $recovered->getBody());
        self::assertCount(2, ClientBuilder::recorded());
    }

    private function challengeBody(): string
    {
        return '<html><body><h1>Access denied</h1>'
            . '<form><div class="g-recaptcha" data-sitekey="example"></div></form>'
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
