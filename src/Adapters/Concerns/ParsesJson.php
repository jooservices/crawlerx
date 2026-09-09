<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Psr\Http\Message\ResponseInterface;

trait ParsesJson
{
    /**
     * @return array<string, mixed>
     */
    protected function jsonFromResponse(ResponseInterface $response, string $site, string $context): array
    {
        $body = (string) $response->getBody();

        if ($response->getStatusCode() >= 400 || trim($body) === '') {
            throw new CrawlBlockedException("{$site} {$context} response is blocked or unavailable.");
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new CrawlParseException("{$site} {$context} response is not valid JSON.");
        }

        return $this->stringKeyed($decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function tryJsonFromResponse(ResponseInterface $response): ?array
    {
        if ($response->getStatusCode() >= 400) {
            return null;
        }

        $body = (string) $response->getBody();
        if (trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $this->stringKeyed($decoded) : null;
    }

    /**
     * @param  array<mixed, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function stringKeyed(array $decoded): array
    {
        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
