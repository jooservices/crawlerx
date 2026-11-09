<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Request;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;

/**
 * Turns a 2xx result whose body lacks the site's ready markers into a failure.
 */
final class ReadyMarkerGate
{
    /** @param list<string> $readyMarkers */
    public static function apply(FetchResultDto $result, array $readyMarkers): FetchResultDto
    {
        if (! $result->ok || $readyMarkers === [] || $result->status < 200 || $result->status >= 300) {
            return $result;
        }

        if (ChallengeDetector::isUsableBody($result->body, $result->status, $readyMarkers)) {
            return $result;
        }

        return new FetchResultDto(
            ok: false,
            body: $result->body,
            status: $result->status,
            methodUsed: $result->methodUsed,
            elapsedMs: $result->elapsedMs,
            challengeDetected: false,
            finalUrl: $result->finalUrl,
            attempts: $result->attempts,
            cookies: $result->cookies,
            headers: $result->headers,
            error: 'ready marker missing',
        );
    }
}
