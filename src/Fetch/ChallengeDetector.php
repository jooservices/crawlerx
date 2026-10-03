<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use Symfony\Component\DomCrawler\Crawler;

final class ChallengeDetector
{
    /**
     * @param  array<string, list<string>|string>  $headers
     */
    public static function isChallenge(string $body, int $status, array $headers = []): bool
    {
        $mitigated = strtolower(self::header($headers, 'cf-mitigated'));
        if (str_contains($mitigated, 'challenge')) {
            return true;
        }

        if (preg_match('/<title>[^<]*(Just a moment|Attention Required)/i', $body) === 1) {
            return true;
        }

        $isJavBusWall = preg_match('/<title>[^<]*Age Verification JavBus/i', $body) === 1
            || str_contains($body, 'driver-verify');
        if ($isJavBusWall) {
            return true;
        }

        if (str_contains($body, 'cf-browser-verification')) {
            return true;
        }

        $looksLikeInterstitial = str_contains($body, 'Just a moment...')
            && str_contains($body, 'challenges.cloudflare.com')
            && strlen($body) < 20000;

        if ($looksLikeInterstitial) {
            return true;
        }

        return in_array($status, [403, 503], true)
            && str_contains($body, 'Just a moment...');
    }

    /** @param list<string> $readyMarkers */
    public static function isUsableBody(string $body, int $status, array $readyMarkers = []): bool
    {
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

    /**
     * @param  array<string, list<string>|string>  $headers
     */
    private static function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) !== 0) {
                continue;
            }

            return is_array($value) ? implode(' ', $value) : $value;
        }

        return '';
    }
}
