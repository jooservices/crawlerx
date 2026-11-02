<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki;

use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Psr\Http\Message\ResponseInterface;

final class AvWikiJson
{
    /**
     * @return array<mixed, mixed>
     */
    public static function decode(ResponseInterface $response, string $context): array
    {
        $body = (string) $response->getBody();
        if ($response->getStatusCode() >= 400 || trim($body) === '') {
            throw new CrawlBlockedException("AV-Wiki {$context} response is blocked or unavailable.");
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new CrawlParseException("AV-Wiki {$context} response is not valid JSON.");
        }

        return $decoded;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function records(array $payload, string $context): array
    {
        if (! array_is_list($payload)) {
            if (self::isRecord($payload)) {
                return [$payload];
            }

            throw new CrawlParseException("AV-Wiki {$context} response does not contain records.");
        }

        $records = [];
        foreach ($payload as $record) {
            if (self::isRecord($record)) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  mixed  $value
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private static function isRecord(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
