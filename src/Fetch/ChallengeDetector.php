<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Support\ChallengeResponseDetector;
use Symfony\Component\DomCrawler\Crawler;

final class ChallengeDetector
{
    /**
     * @param  array<string, list<string>|string>  $headers
     */
    public static function isChallenge(string $body, int $status, array $headers = []): bool
    {
        return ChallengeResponseDetector::isChallenge($body, $status, $headers);
    }

    /** @param list<string> $readyMarkers */
    public static function isUsableBody(string $body, int $status, array $readyMarkers = []): bool
    {
        if ($status >= 400) {
            return false;
        }

        $trimmed = trim($body);
        if ($trimmed === '') {
            return false;
        }

        $isJson = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
        if ($isJson) {
            return json_decode($trimmed) !== null;
        }

        if ($readyMarkers !== [] && $status >= 200 && $status < 300) {
            foreach ($readyMarkers as $marker) {
                if (self::bodyContainsMarker($body, $marker)) {
                    return true;
                }
            }

            return false;
        }

        return strlen($trimmed) >= 32;
    }

    public static function bodyContainsMarker(string $body, string $marker): bool
    {
        $selectors = preg_split('/\s*,\s*/', $marker);
        if ($selectors === false) {
            return false;
        }

        foreach ($selectors as $selector) {
            if (self::bodyContainsSelector($body, trim($selector))) {
                return true;
            }
        }

        return false;
    }

    private static function bodyContainsSelector(string $body, string $selector): bool
    {
        if ($selector === '') {
            return false;
        }

        try {
            return (new Crawler($body))->filter($selector)->count() > 0;
        } catch (\Throwable) {
            // Text markers are handled by TerminalStatus against visible text.
            // A raw-body fallback would also match content inside scripts.
            return false;
        }
    }
}
