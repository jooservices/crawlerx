<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

final class ChallengeResponseDetector
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
