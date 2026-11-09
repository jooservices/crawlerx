<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Cache;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Http\SeededCrawlHttpClient;
use JOOservices\CrawlerX\Services\ClientFactory;
use PHPUnit\Framework\TestCase;

final class RequestScopedResponseMemoTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_reuses_successful_follow_up_responses_within_one_crawl(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/follow-up';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, ['X-Page' => 'follow-up'], 'fixture response body'))
            ->push(TestResponse::make(200, ['X-Page' => 'unexpected second response'], 'different response body')));
        $client = $this->client();

        $first = $client->get($url)->toPsrResponse();
        $second = $client->get($url)->toPsrResponse();

        self::assertSame('fixture response body', (string) $first->getBody());
        self::assertSame('fixture response body', (string) $second->getBody());
        self::assertSame('follow-up', $second->getHeaderLine('X-Page'));
        self::assertCount(1, ClientBuilder::recorded());
    }

    public function test_response_keys_include_url_and_request_headers(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/follow-up';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], 'english fixture response body'))
            ->push(TestResponse::make(200, [], 'japanese fixture response body')));
        ClientBuilder::respond('GET', 'https://example.test/other', (new TestResponseSequence())
            ->push(TestResponse::make(200, [], 'other URL fixture response body')));
        $client = $this->client();

        $english = $client->get($url, ['Accept-Language' => 'en'])->toPsrResponse();
        $japanese = $client->get($url, ['Accept-Language' => 'ja'])->toPsrResponse();
        $otherUrl = $client->get('https://example.test/other')->toPsrResponse();
        $cachedEnglish = $client->get($url, ['accept-language' => 'en'])->toPsrResponse();

        self::assertSame('english fixture response body', (string) $english->getBody());
        self::assertSame('japanese fixture response body', (string) $japanese->getBody());
        self::assertSame('other URL fixture response body', (string) $otherUrl->getBody());
        self::assertSame('english fixture response body', (string) $cachedEnglish->getBody());
        self::assertCount(3, ClientBuilder::recorded());
    }

    public function test_response_keys_use_last_case_insensitive_header_value(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/follow-up';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], 'first effective header response'))
            ->push(TestResponse::make(200, [], 'second effective header response')));
        $client = $this->client();

        $first = $client->get($url, ['X-Test' => 'a', 'x-test' => 'b'])->toPsrResponse();
        $second = $client->get($url, ['x-test' => 'b', 'X-Test' => 'a'])->toPsrResponse();

        self::assertSame('first effective header response', (string) $first->getBody());
        self::assertSame('second effective header response', (string) $second->getBody());
        self::assertCount(2, ClientBuilder::recorded());
    }

    public function test_non_cacheable_seed_responses_fall_back_to_the_inner_client(): void
    {
        ClientBuilder::fake();
        $cases = [
            ['captcha', 200, false, '<html>captcha page</html>'],
            ['challenge', 200, true, '<html>challenge page</html>'],
            ['unavailable', 503, false, 'temporary unavailable'],
        ];

        foreach ($cases as [$path, $status, $challengeDetected, $body]) {
            $url = 'https://example.test/' . $path;
            ClientBuilder::respond('GET', $url, (new TestResponseSequence())
                ->push(TestResponse::make(200, [], 'fresh inner response')));
            $client = $this->client($url, $body, $status, $challengeDetected);

            $first = $client->get($url)->toPsrResponse();
            $second = $client->get($url)->toPsrResponse();

            self::assertSame('fresh inner response', (string) $first->getBody());
            self::assertSame('fresh inner response', (string) $second->getBody());
        }

        self::assertCount(3, ClientBuilder::recorded());
    }

    public function test_non_success_and_challenge_responses_are_not_memoized(): void
    {
        ClientBuilder::fake();
        $cases = [
            ['/not-found', 404, [], 'not found body'],
            ['/challenge', 200, ['cf-mitigated' => 'challenge'], '<html>challenge page</html>'],
            ['/captcha', 200, [], '<html>captcha page</html>'],
        ];

        foreach ($cases as [$path, $status, $headers, $body]) {
            $url = 'https://example.test' . $path;
            ClientBuilder::respond('GET', $url, (new TestResponseSequence())
                ->push(TestResponse::make($status, $headers, $body))
                ->push(TestResponse::make($status, $headers, $body)));
        }

        $client = $this->client();
        foreach ($cases as [$path]) {
            $url = 'https://example.test' . $path;
            $client->get($url);
            $client->get($url);
        }

        self::assertCount(6, ClientBuilder::recorded());
    }

    public function test_memo_is_discarded_with_the_crawl_client(): void
    {
        ClientBuilder::fake();
        $url = 'https://example.test/follow-up';
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, [], 'first crawl fixture response'))
            ->push(TestResponse::make(200, [], 'second crawl fixture response')));

        $firstCrawl = $this->client();
        $firstCrawl->get($url);
        $firstCrawl->get($url);
        $secondCrawl = $this->client();
        $response = $secondCrawl->get($url)->toPsrResponse();

        self::assertSame('second crawl fixture response', (string) $response->getBody());
        self::assertCount(2, ClientBuilder::recorded());
    }

    private function client(
        string $url = 'https://example.test/seed',
        string $body = 'seeded fixture response body',
        int $status = 200,
        bool $challengeDetected = false,
    ): SeededCrawlHttpClient {
        $fetch = new FetchResultDto(
            ok: true,
            body: $body,
            status: $status,
            methodUsed: FetchMethod::Http,
            elapsedMs: 1,
            challengeDetected: $challengeDetected,
            finalUrl: $url,
        );

        return new SeededCrawlHttpClient((new ClientFactory())->factory(), $fetch);
    }
}
