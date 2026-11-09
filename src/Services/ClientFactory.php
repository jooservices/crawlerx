<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use Closure;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Client\HttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Http\ClientCrawlHttpClient;
use JOOservices\CrawlerX\Http\PrefetchedCrawlHttpClient;

final class ClientFactory
{
    private const DEFAULT_CONNECT_TIMEOUT = 5.0;

    /** @var array<string, HttpClient> */
    private static array $clients = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function factory(array $options = [], ?string $site = null): CrawlHttpClient
    {
        if (isset($options['prefetched_html']) && is_string($options['prefetched_html']) && $options['prefetched_html'] !== '') {
            $status = is_numeric($options['prefetched_status'] ?? null) ? (int) $options['prefetched_status'] : 200;
            $finalUrl = is_string($options['prefetched_final_url'] ?? null) && $options['prefetched_final_url'] !== ''
                ? $options['prefetched_final_url']
                : null;

            return new PrefetchedCrawlHttpClient($options['prefetched_html'], $status, $finalUrl);
        }

        $headers = [];
        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $headers[(string) $name] = (string) $value;
            }
        }

        $client = $this->client($options, $site);

        return new ClientCrawlHttpClient($client, $headers);
    }

    public static function boundedHttpClient(float $timeout, int $maxResponseBytes): HttpClient
    {
        $builder = ClientBuilder::create()->withTimeout($timeout);
        if (! ClientBuilder::isFaked()) {
            $builder = $builder->withTransport(new BoundedCurlTransport($maxResponseBytes));
        }

        return $builder->build();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function client(array $options, ?string $site): HttpClient
    {
        $isFaked = ClientBuilder::isFaked();
        $cacheKey = $isFaked ? null : $this->cacheKey($site, $options);
        if ($cacheKey !== null && isset(self::$clients[$cacheKey])) {
            return self::$clients[$cacheKey];
        }

        $builder = ClientBuilder::create();
        if (! $isFaked) {
            $builder = $builder
                ->withConnectTimeout($this->connectTimeout($options))
                ->withCompression();
        }

        if (isset($options['base_uri']) && is_string($options['base_uri'])) {
            $builder = $builder->withBaseUri($options['base_uri']);
        }

        if (! ClientBuilder::isFaked() && isset($options['timeout']) && (is_int($options['timeout']) || is_float($options['timeout']))) {
            $builder = $builder->withTimeout((float) $options['timeout']);
        }

        if (! ClientBuilder::isFaked() && isset($options['verify_ssl']) && is_bool($options['verify_ssl'])) {
            $builder = $builder->withVerifySsl($options['verify_ssl']);
        }

        $proxy = self::resolveProxy($options);
        if (! $isFaked) {
            $builder = $builder->withRedirects($this->allowRedirects($options));
        }
        if (! $isFaked && $proxy !== null) {
            $builder = $builder->withProxy($proxy);
        }
        $maxResponseBytes = $options['max_response_bytes'] ?? null;
        if (! $isFaked && is_int($maxResponseBytes) && $maxResponseBytes > 0) {
            $beforeRequest = $options['before_request'] ?? null;
            $builder = $builder->withTransport(new BoundedCurlTransport(
                $maxResponseBytes,
                $beforeRequest instanceof Closure ? $beforeRequest : null,
            ));
        }

        /** @var HttpClient $client */
        $client = $builder->build();

        if ($cacheKey !== null) {
            self::$clients[$cacheKey] = $client;
        }

        return $client;
    }

    /**
     * Sites that throttle per client delay the response from the connection phase on, and libcurl reports
     * that wait as a connect timeout, so the connect timeout must be configurable next to the total timeout.
     *
     * @param  array<string, mixed>  $options
     */
    private function connectTimeout(array $options): float
    {
        $value = $options['connect_timeout'] ?? null;

        return (is_int($value) || is_float($value)) && $value > 0 ? (float) $value : self::DEFAULT_CONNECT_TIMEOUT;
    }

    /**
     * The outbound proxy for a client: the explicit `proxy` option, else the process-wide CRAWLERX_HTTP_PROXY.
     * Unset or blank means a direct connection. The URL may carry credentials, so it is never logged.
     *
     * @param  array<string, mixed>  $options
     */
    public static function resolveProxy(array $options): ?string
    {
        $explicit = $options['proxy'] ?? null;
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $env = getenv('CRAWLERX_HTTP_PROXY');

        return is_string($env) && trim($env) !== '' ? trim($env) : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function cacheKey(?string $site, array $options): ?string
    {
        if ($site === null) {
            return null;
        }

        $transportOptions = [
            'timeout' => is_int($options['timeout'] ?? null) || is_float($options['timeout'] ?? null)
                ? (float) $options['timeout']
                : 30.0,
            'connect_timeout' => $this->connectTimeout($options),
            'verify_ssl' => is_bool($options['verify_ssl'] ?? null) ? $options['verify_ssl'] : true,
            'allow_redirects' => $this->allowRedirects($options),
        ];
        if (is_int($options['max_response_bytes'] ?? null)) {
            $transportOptions['max_response_bytes'] = $options['max_response_bytes'];
        }
        if (($options['before_request'] ?? null) instanceof Closure) {
            $transportOptions['before_request'] = spl_object_id($options['before_request']);
        }
        if (is_string($options['base_uri'] ?? null)) {
            $transportOptions['base_uri'] = $options['base_uri'];
        }
        $proxy = self::resolveProxy($options);
        if ($proxy !== null) {
            $transportOptions['proxy'] = $proxy;
        }
        $optionsHash = hash('sha256', json_encode($transportOptions, JSON_THROW_ON_ERROR));

        return $site . ':' . $optionsHash;
    }

    /** @param array<string, mixed> $options
     *  @return bool|array<string, mixed>
     */
    private function allowRedirects(array $options): bool|array
    {
        if (self::resolveProxy($options) !== null) {
            return false;
        }

        $configured = $options['allow_redirects'] ?? null;
        if (is_array($configured)) {
            $redirectOptions = [];
            foreach ($configured as $key => $value) {
                if (! is_string($key)) {
                    return false;
                }

                $redirectOptions[$key] = $value;
            }

            return $redirectOptions;
        }

        return is_bool($configured) ? $configured : true;
    }
}
