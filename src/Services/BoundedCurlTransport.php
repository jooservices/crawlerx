<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use Closure;
use JOOservices\Client\Contracts\TransportCapabilities;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Exceptions\RequestException;
use JOOservices\Client\Support\RedirectHandler;
use JOOservices\Client\Transport\Curl\CurlExchange;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Adds an application response limit while retaining the client's redirect policy. */
final class BoundedCurlTransport implements TransportInterface
{
    private readonly RedirectHandler $redirects;

    private readonly CurlExchange $exchange;

    /**
     * @param Closure(RequestInterface): void|null $beforeRequest
     */
    public function __construct(
        int $maxResponseBytes,
        private readonly ?Closure $beforeRequest = null,
    ) {
        if ($maxResponseBytes < 1) {
            throw new InvalidConfigurationException('The response body limit must be a positive number of bytes.');
        }

        $factory = new Psr17Factory();
        $this->redirects = new RedirectHandler($factory, $factory);
        $this->exchange = new CurlExchange($factory, $factory, maxResponseBytes: $maxResponseBytes);
    }

    public function capabilities(): TransportCapabilities
    {
        return new TransportCapabilities([
            'timeout' => true,
            'connectTimeout' => true,
            'compression' => true,
            'proxy' => true,
            'verifySsl' => true,
            'allowRedirects' => true,
        ]);
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        $scheme = strtolower($request->getUri()->getScheme());
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new RequestException($request, 'CurlTransport only sends HTTP and HTTPS requests.');
        }

        return $this->redirects->send(
            $request,
            $options,
            function (RequestInterface $current, RequestOptions $inner, ?array $pinnedAddresses): ResponseInterface {
                ($this->beforeRequest)?->__invoke($current);

                return $this->exchange->send($current, $inner, $pinnedAddresses);
            },
        );
    }

    public function __destruct()
    {
        $this->exchange->close();
    }
}
