<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Client\HttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Http\ClientCrawlHttpClient;
use JOOservices\CrawlerX\Http\PrefetchedCrawlHttpClient;

final class ClientFactory
{
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
                ->withConnectTimeout(5)
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

        /** @var HttpClient $client */
        $client = $builder->build();

        if ($cacheKey !== null) {
            self::$clients[$cacheKey] = $client;
        }

        return $client;
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
            'verify_ssl' => is_bool($options['verify_ssl'] ?? null) ? $options['verify_ssl'] : true,
        ];
        if (is_string($options['base_uri'] ?? null)) {
            $transportOptions['base_uri'] = $options['base_uri'];
        }
        $optionsHash = hash('sha256', json_encode($transportOptions, JSON_THROW_ON_ERROR));

        return $site . ':' . $optionsHash;
    }
}
