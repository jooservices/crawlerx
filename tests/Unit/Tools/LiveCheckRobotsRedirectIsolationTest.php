<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
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

final class LiveCheckRobotsRedirectIsolationTest extends TestCase
{
    public function test_robot_redirect_hops_count_toward_the_adapter_request_budget(): void
    {
        $transport = new LiveCheckRobotsRedirectRecordingTransport([
            new Response(302, ['Location' => '/robots-final']),
            new Response(200, [], "User-agent: *\nAllow: /\n"),
            new Response(200, [], 'sample page'),
        ]);
        $now = 0;
        $permittedAt = [];
        $attemptedUrls = [];
        $limiter = new LiveCheckRequestLimiter(
            static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            static function () use (&$now): int {
                return $now;
            },
        );
        $httpClient = ClientBuilder::create()->withTransport($transport)->build();
        $beforeRobotsRequest = static function (string $url) use ($limiter, &$attemptedUrls, &$permittedAt, &$now): bool {
            $attemptedUrls[] = $url;
            $allowed = $limiter->beforeRobotsRequest($url);
            if ($allowed) {
                $permittedAt[] = $now;
            }

            return $allowed;
        };
        $robotsClient = new ClientCrawlHttpClient(
            $httpClient,
            beforeOutboundRequest: $beforeRobotsRequest,
        );
        $sampleClient = new ClientCrawlHttpClient(
            $httpClient,
            beforeOutboundRequest: static function (string $url) use ($limiter, &$attemptedUrls, &$permittedAt, &$now): bool {
                $attemptedUrls[] = $url;
                $allowed = $limiter->beforeRequest($url);
                if ($allowed) {
                    $permittedAt[] = $now;
                }

                return $allowed;
            },
        );

        $limiter->forAdapter('sample-site', static function () use ($robotsClient, $sampleClient): void {
            $robotsClient->get('https://example.test/robots.txt');
            $sampleClient->get('https://example.test/item/1');
            $sampleClient->get('https://example.test/item/2');
        });

        self::assertSame([
            'https://example.test/robots.txt',
            'https://example.test/robots-final',
            'https://example.test/item/1',
        ], $transport->requestedUrls());
        self::assertSame([
            'https://example.test/robots.txt',
            'https://example.test/robots-final',
            'https://example.test/item/1',
            'https://example.test/item/2',
        ], $attemptedUrls);
        self::assertCount(3, $permittedAt);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[1] - $permittedAt[0]);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[2] - $permittedAt[1]);
        self::assertTrue($limiter->wasBudgetExceeded('sample-site'));
    }

    public function test_unsupported_robots_redirect_does_not_hide_a_successful_sample(): void
    {
        $transport = new LiveCheckRobotsRedirectRecordingTransport([
            new Response(302, ['Location' => 'http://127.0.0.1/robots.txt']),
            new Response(200, [], 'sample page'),
        ]);
        $now = 0;
        $limiter = new LiveCheckRequestLimiter(
            static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            static function () use (&$now): int {
                return $now;
            },
        );
        $httpClient = ClientBuilder::create()->withTransport($transport)->build();
        $robotsClient = new ClientCrawlHttpClient(
            $httpClient,
            beforeOutboundRequest: $limiter->beforeRobotsRequest(...),
        );
        $sampleClient = new ClientCrawlHttpClient(
            $httpClient,
            beforeOutboundRequest: $limiter->beforeRequest(...),
            onUnsupportedRedirect: $limiter->markUnsupportedRedirect(...),
        );
        $sample = [
            'slug' => 'sample-site',
            'adapter_name' => 'Sample Site',
            'role' => 'detail',
            'name' => 'sample',
            'type' => CrawlType::Detail,
            'url' => 'https://example.test/item/1',
        ];
        $runner = new LiveCheckReportRunner(
            crawl: static function (array $currentSample) use ($robotsClient, $sampleClient, $limiter): CrawlOutcomeDto {
                return $limiter->forAdapter($currentSample['slug'], static function () use ($currentSample, $robotsClient, $sampleClient, $limiter): CrawlOutcomeDto {
                    $robotsClient->get('https://example.test/robots.txt');
                    $response = $sampleClient->get($currentSample['url'])->toPsrResponse();

                    if ($limiter->wasRedirectUnsupported($currentSample['slug'])) {
                        throw new LiveCheckRequestLimitException(reason: 'redirect_unsupported');
                    }

                    self::assertSame(200, $response->getStatusCode());

                    return CrawlOutcomeDto::success(new CrawlItemResultDto(
                        url: $currentSample['url'],
                        entityType: 'movie',
                        meta: ['movie' => ['title' => 'Sample title']],
                    ));
                });
            },
            sleepMicroseconds: static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            clockNanoseconds: static function () use (&$now): int {
                return $now;
            },
        );

        $result = $runner->run([$sample])[0];

        self::assertSame('ok', $result['status']);
        self::assertSame([
            'https://example.test/robots.txt',
            'https://example.test/item/1',
        ], $transport->requestedUrls());
    }
}

final class LiveCheckRobotsRedirectRecordingTransport implements TransportInterface
{
    /** @var list<RequestInterface> */
    private array $requests = [];

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
        $this->requests[] = $request;

        return array_shift($this->responses) ?? new Response(500);
    }

    /** @return list<string> */
    public function requestedUrls(): array
    {
        return array_map(static fn(RequestInterface $request): string => (string) $request->getUri(), $this->requests);
    }
}
