<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use JOOservices\CrawlerX\Dto\RefusedRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Psr\Http\Message\ResponseInterface;

trait ParsesJson
{
    /**
     * @param  int  $startedAt  when the request was sent, in milliseconds since the epoch, so a refusal is recorded with its duration
     * @return array<string, mixed>
     */
    protected function jsonFromResponse(ResponseInterface $response, string $site, string $context, int $startedAt): array
    {
        $body = (string) $response->getBody();
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw $this->refused("{$site} {$context} response is blocked or unavailable (HTTP {$status}).", $status, "HTTP {$status}", $startedAt);
        }

        if (trim($body) === '') {
            throw $this->refused("{$site} {$context} response is blocked or unavailable (empty body).", $status, 'empty body', $startedAt);
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new CrawlParseException("{$site} {$context} response is not valid JSON.");
        }

        return $this->stringKeyed($decoded);
    }

    private function refused(string $message, int $status, string $reason, int $startedAt): CrawlBlockedException
    {
        return new CrawlBlockedException($message, refused: new RefusedRequestDto($status, $reason, $startedAt));
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
