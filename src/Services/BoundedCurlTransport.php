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
        $proxyOrigin = $options->proxy === null ? null : $this->origin((string) $request->getUri());
        if ($proxyOrigin === '') {
            throw new RequestException($request, 'A proxied request must use an HTTP or HTTPS origin.');
        }

        return $this->redirects->send(
            $request,
            $options,
            function (RequestInterface $current, RequestOptions $inner, ?array $pinnedAddresses) use ($proxyOrigin): ResponseInterface {
                if ($proxyOrigin !== null && $this->origin((string) $current->getUri()) !== $proxyOrigin) {
                    throw new RequestException($current, 'A proxied redirect changed origin.');
                }

                ($this->beforeRequest)?->__invoke($current);

                // An empty CURLOPT_PROXY disables libcurl's implicit http_proxy,
                // https_proxy and ALL_PROXY environment handling. Only an
                // explicitly configured CrawlerX proxy may be used.
                $transportOptions = $inner->proxy === null
                    ? new RequestOptions(
                        timeout: $inner->timeout,
                        connectTimeout: $inner->connectTimeout,
                        proxy: '',
                        verifySsl: $inner->verifySsl,
                        allowRedirects: $inner->allowRedirects,
                        extra: $inner->extra,
                        compression: $inner->compression,
                    )
                    : $inner;

                return $this->exchange->send($current, $transportOptions, $pinnedAddresses);
            },
        );
    }

    public function __destruct()
    {
        $this->exchange->close();
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = is_string($parts['scheme'] ?? null) ? strtolower($parts['scheme']) : '';
        $host = is_string($parts['host'] ?? null) ? strtolower($parts['host']) : '';
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme . '://' . $host . ':' . $port;
    }
}
