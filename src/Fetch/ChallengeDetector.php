<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

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

        $first = preg_split('/\s+/', $selector, 2)[0] ?? $selector;
        $tag = preg_match('/^[a-z][a-z0-9:-]*/i', $first, $tagMatch) === 1 ? $tagMatch[0] : null;
        $id = preg_match('/#([a-z][a-z0-9:_-]*)/i', $first, $idMatch) === 1 ? $idMatch[1] : null;
        preg_match_all('/\.([a-z][a-z0-9:_-]*)/i', $first, $classMatch);

        if ($tag !== null && preg_match('/<' . preg_quote($tag, '/') . '\b/i', $body) !== 1) {
            return false;
        }

        if ($id !== null && preg_match('/\bid\s*=\s*["\']' . preg_quote($id, '/') . '["\']/i', $body) !== 1) {
            return false;
        }

        preg_match_all('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $body, $classAttributes);
        $classes = [];
        foreach ($classAttributes[1] as $attribute) {
            $tokens = preg_split('/\s+/', trim($attribute));
            if ($tokens !== false) {
                $classes = array_merge($classes, $tokens);
            }
        }

        foreach ($classMatch[1] as $class) {
            if (! in_array($class, $classes, true)) {
                return false;
            }
        }

        if ($tag !== null || $id !== null || $classMatch[1] !== []) {
            return true;
        }

        return str_contains($body, $selector);
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
