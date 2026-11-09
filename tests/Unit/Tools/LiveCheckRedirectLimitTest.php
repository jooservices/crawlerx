<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Http\ClientCrawlHttpClient;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimitException;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimiter;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimitException.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimiter.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportRunner.php';

final class LiveCheckRedirectLimitTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_redirect_hops_are_limited_and_the_final_response_is_returned(): void
    {
        $transport = new LiveCheckRedirectRecordingTransport([
            new Response(302, ['Location' => '/final']),
            new Response(200, [], 'final page'),
        ]);
        $now = 0;
        $permittedAt = [];
        $limiter = $this->limiter($now);
        $client = new ClientCrawlHttpClient(
            ClientBuilder::create()->withTransport($transport)->build(),
            beforeOutboundRequest: static function (string $url) use ($limiter, &$permittedAt, &$now): bool {
                $allowed = $limiter->beforeRequest($url);
                if ($allowed) {
                    $permittedAt[] = $now;
                }

                return $allowed;
            },
        );

        $response = $limiter->forAdapter('sample-site', static fn() => $client->get(
            'https://example.test/start',
        )->toPsrResponse());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['https://example.test/start', 'https://example.test/final'], $transport->requestedUrls());
        self::assertCount(2, $permittedAt);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[1] - $permittedAt[0]);
        self::assertCount(2, $transport->requests);
        self::assertFalse($transport->requests[0][1]->allowRedirects);
        self::assertFalse($transport->requests[1][1]->allowRedirects);
    }

    public function test_redirect_blocked_by_the_budget_is_reported_as_not_checked(): void
    {
        $transport = new LiveCheckRedirectRecordingTransport([
            new Response(302, ['Location' => '/final']),
        ]);
        $now = 0;
        $limiter = $this->limiter($now);
        $client = new ClientCrawlHttpClient(
            ClientBuilder::create()->withTransport($transport)->build(),
            beforeOutboundRequest: $limiter->beforeRequest(...),
        );
        $sample = [
            'slug' => 'sample-site',
            'adapter_name' => 'Sample Site',
            'role' => 'detail',
            'name' => 'detail.html',
            'type' => CrawlType::Detail,
            'url' => 'https://example.test/start',
        ];
        $limiter->forAdapter('sample-site', static fn() => $limiter->beforeRequest('https://example.test/prior'));
        $runner = new LiveCheckReportRunner(
            crawl: static function (array $currentSample) use ($client, $limiter): CrawlOutcomeDto {
                $limiter->forAdapter($currentSample['slug'], static fn() => $client->get($currentSample['url']));
                if ($limiter->wasBudgetExceeded($currentSample['slug'])) {
                    throw new LiveCheckRequestLimitException();
                }

                return CrawlOutcomeDto::failure(new CrawlErrorDto(CrawlErrorCode::Network, 'Unexpected response.'));
            },
            sleepMicroseconds: static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            clockNanoseconds: static function () use (&$now): int {
                return $now;
            },
        );

        $result = $runner->run([$sample])[0];

        self::assertSame('not-checked', $result['status']);
        self::assertSame('request_limit', $result['error_code']);
        self::assertSame(['https://example.test/start'], $transport->requestedUrls());
        self::assertCount(1, $transport->requests);
    }

    public function test_cross_host_redirect_that_cannot_keep_dns_pinning_is_not_checked(): void
    {
        $transport = new LiveCheckRedirectRecordingTransport([
            new Response(302, ['Location' => 'https://127.0.0.1/final']),
        ]);
        $now = 0;
        $limiter = $this->limiter($now);
        $client = new ClientCrawlHttpClient(
            ClientBuilder::create()->withTransport($transport)->build(),
            beforeOutboundRequest: $limiter->beforeRequest(...),
            onUnsupportedRedirect: $limiter->markUnsupportedRedirect(...),
        );
        $sample = [
            'slug' => 'sample-site',
            'adapter_name' => 'Sample Site',
            'role' => 'detail',
            'name' => 'detail.html',
            'type' => CrawlType::Detail,
            'url' => 'https://example.test/start',
        ];
        $runner = new LiveCheckReportRunner(
            crawl: static function (array $currentSample) use ($client, $limiter): CrawlOutcomeDto {
                $limiter->forAdapter($currentSample['slug'], static fn() => $client->get($currentSample['url']));
                if ($limiter->wasRedirectUnsupported($currentSample['slug'])) {
                    throw new LiveCheckRequestLimitException(reason: 'redirect_unsupported');
                }

                return CrawlOutcomeDto::failure(new CrawlErrorDto(CrawlErrorCode::ParseFailed, 'Unexpected response.'));
            },
            sleepMicroseconds: static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            clockNanoseconds: static function () use (&$now): int {
                return $now;
            },
        );

        $result = $runner->run([$sample])[0];

        self::assertSame('not-checked', $result['status']);
        self::assertSame('redirect_unsupported', $result['error_code']);
        self::assertSame(['https://example.test/start'], $transport->requestedUrls());
        self::assertCount(1, $transport->requests);
    }

    private function limiter(int &$now): LiveCheckRequestLimiter
    {
        return new LiveCheckRequestLimiter(
            static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            static function () use (&$now): int {
                return $now;
            },
        );
    }
}

final class LiveCheckRedirectRecordingTransport implements TransportInterface
{
    /** @var list<array{RequestInterface, RequestOptions}> */
    public array $requests = [];

    /** @param list<ResponseInterface> $responses */
    public function __construct(private array $responses)
    {
    }

    public function capabilities(): TransportCapabilities
    {
        return new TransportCapabilities(['allowRedirects' => true]);
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        $this->requests[] = [$request, $options];

        return array_shift($this->responses) ?? new Response(500);
    }

    /** @return list<string> */
    public function requestedUrls(): array
    {
        return array_map(static fn(array $request): string => (string) $request[0]->getUri(), $this->requests);
    }
}
