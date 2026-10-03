<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;

final readonly class TerminalStatus
{
    public function __construct(
        public CrawlErrorCode $code,
        public bool $retryable,
        public ?int $retryAfterSeconds = null,
    ) {
    }

    /** @param list<string> $soft404Markers */
    public static function fromResult(FetchResultDto $result, array $soft404Markers = []): ?self
    {
        if ($result->status === 404) {
            return new self(CrawlErrorCode::NotFound, false);
        }

        if ($result->status === 410) {
            return new self(CrawlErrorCode::Gone, false);
        }

        if ($result->status === 429 || ($result->status === 503 && self::retryAfter($result) !== null)) {
            return new self(CrawlErrorCode::RateLimited, true, self::retryAfter($result));
        }

        foreach ($soft404Markers as $marker) {
            if (
                self::bodyContainsMarker($result->body, $marker)
                || str_contains(self::visibleText($result->body), strtolower(trim($marker)))
            ) {
                return new self(CrawlErrorCode::NotFound, false);
            }
        }

        return null;
    }

    private static function retryAfter(FetchResultDto $result): ?int
    {
        foreach ($result->headers as $name => $values) {
            if (strcasecmp($name, 'Retry-After') !== 0) {
                continue;
            }

            $value = trim($values[0] ?? '');
            if (ctype_digit(trim($value))) {
                return max(0, (int) $value);
            }
        }

        return null;
    }

    private static function bodyContainsMarker(string $body, string $marker): bool
    {
        return ChallengeDetector::bodyContainsMarker($body, $marker);
    }

    private static function visibleText(string $body): string
    {
        $withoutNonVisible = preg_replace(
            '/<(script|style|template)\b[^>]*>.*?<\/\1\s*>/is',
            ' ',
            $body,
        ) ?? $body;
        $text = html_entity_decode(strip_tags($withoutNonVisible), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
