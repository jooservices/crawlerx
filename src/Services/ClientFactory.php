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
    /**
     * @param  array<string, mixed>  $options
     */
    public function factory(array $options = []): CrawlHttpClient
    {
        if (isset($options['prefetched_html']) && is_string($options['prefetched_html']) && $options['prefetched_html'] !== '') {
            $status = is_numeric($options['prefetched_status'] ?? null) ? (int) $options['prefetched_status'] : 200;
            $finalUrl = is_string($options['prefetched_final_url'] ?? null) && $options['prefetched_final_url'] !== ''
                ? $options['prefetched_final_url']
                : null;

            return new PrefetchedCrawlHttpClient($options['prefetched_html'], $status, $finalUrl);
        }

        $builder = ClientBuilder::create();

        if (isset($options['base_uri']) && is_string($options['base_uri'])) {
            $builder->withBaseUri($options['base_uri']);
        }

        if (isset($options['timeout']) && is_int($options['timeout'])) {
            $builder->withTimeout($options['timeout']);
        }

        if (isset($options['verify_ssl']) && is_bool($options['verify_ssl'])) {
            $builder->withVerifySsl($options['verify_ssl']);
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $builder->withHeader((string) $name, (string) $value);
            }
        }

        /** @var HttpClient $client */
        $client = $builder->build();

        return new ClientCrawlHttpClient($client);
    }
}
