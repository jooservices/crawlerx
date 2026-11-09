<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\CrawlerX\Http\ClientCrawlHttpClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class LiveCheckRedirectLimitTest extends TestCase
{
    public function test_limited_http_requests_do_not_follow_redirects_behind_the_limiter(): void
    {
        $transport = new LiveCheckRedirectRecordingTransport();
        $requestedUrls = [];
        $httpClient = ClientBuilder::create()->withTransport($transport)->build();
        $client = new ClientCrawlHttpClient($httpClient, beforeOutboundRequest: static function (string $url) use (&$requestedUrls): bool {
            $requestedUrls[] = $url;

            return true;
        });

        $response = $client->get('https://example.test/start')->toPsrResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['https://example.test/start'], $requestedUrls);
        self::assertCount(1, $transport->requests);
        self::assertFalse($transport->requests[0][1]->allowRedirects);
    }
}

final class LiveCheckRedirectRecordingTransport implements TransportInterface
{
    /** @var list<array{RequestInterface, RequestOptions}> */
    public array $requests = [];

    public function capabilities(): TransportCapabilities
    {
        return new TransportCapabilities(['allowRedirects' => true]);
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        $this->requests[] = [$request, $options];

        return new Response(302, ['Location' => 'https://example.test/final']);
    }
}
