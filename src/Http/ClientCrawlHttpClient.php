<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use Closure;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Client\HttpClient;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Support\RedirectHandler;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final readonly class ClientCrawlHttpClient implements CrawlHttpClient
{
    /**
     * @param array<string, string> $headers
     * @param Closure(string): bool|null $beforeOutboundRequest
     * @param Closure(): void|null $onUnsupportedRedirect
     */
    public function __construct(
        private HttpClient $httpClient,
        private array $headers = [],
        private ?Closure $beforeOutboundRequest = null,
        private ?Closure $onUnsupportedRedirect = null,
    ) {
    }

    /** Sends a GET request and routes each supported redirect hop through the limiter.
     *
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): CrawlHttpResponse
    {
        $builder = $this->httpClient->requestBuilder()->get($url);
        if ($this->beforeOutboundRequest !== null && ! ClientBuilder::isFaked()) {
            $builder = $builder->withRedirects(['max' => 10]);
        }

        $merged = array_merge($this->headers, $headers);
        if ($merged !== []) {
            $builder = $builder->withHeaders($merged);
        }
        $request = $builder->build();

        if ($this->beforeOutboundRequest === null) {
            return new PsrCrawlHttpResponse($this->httpClient->send($request->toPsr(), $request->options()));
        }
        if (ClientBuilder::isFaked()) {
            if (! ($this->beforeOutboundRequest)($url)) {
                return $this->requestLimitResponse();
            }

            return new PsrCrawlHttpResponse($this->httpClient->send($request->toPsr(), $request->options()));
        }

        $factory = new Psr17Factory();
        $redirects = new RedirectHandler($factory, $factory);
        try {
            $response = $redirects->send(
                $request->toPsr(),
                $request->options(),
                function (RequestInterface $current, RequestOptions $options, ?array $pinnedAddresses): ResponseInterface {
                    if ($pinnedAddresses !== null) {
                        $this->markUnsupportedRedirect();

                        return new Response(302, ['X-CrawlerX-Redirect-Unsupported' => 'cross-host target requires DNS pinning']);
                    }

                    if (! ($this->beforeOutboundRequest)((string) $current->getUri())) {
                        return $this->requestLimitResponse()->toPsrResponse();
                    }

                    try {
                        return $this->httpClient->send($current, $options);
                    } catch (RequestException $exception) {
                        throw new RuntimeException('The HTTP client rejected the outbound request.', 0, $exception);
                    }
                },
            );
        } catch (RequestException) {
            $this->markUnsupportedRedirect();
            $response = new Response(302, ['X-CrawlerX-Redirect-Unsupported' => 'redirect target was not followed']);
        }

        return new PsrCrawlHttpResponse($response);
    }

    /** Creates the synthetic response used when another outbound request is not allowed. */
    private function requestLimitResponse(): PsrCrawlHttpResponse
    {
        return new PsrCrawlHttpResponse(new Response(429, [
            'Retry-After' => '0',
            'X-CrawlerX-Request-Limit' => 'exceeded',
        ]));
    }

    /** Records that this transport could not safely follow a redirect target. */
    private function markUnsupportedRedirect(): void
    {
        if ($this->onUnsupportedRedirect !== null) {
            ($this->onUnsupportedRedirect)();
        }
    }
}
