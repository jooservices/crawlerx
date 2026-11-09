<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Request;

use JOOservices\CrawlerX\Dto\FetchResultDto;

/**
 * Tells whether a fetch result is a login wall rather than the requested page.
 */
final class AuthWallDetector
{
    private const MARKERS = ['login required', 'authentication required', 'please log in', 'please login', 'sign in required'];

    public static function isAuthRequired(FetchResultDto $result): bool
    {
        if ($result->status === 401) {
            return true;
        }

        $visible = strtolower(trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags(preg_replace('/<(script|style|template)\b[^>]*>.*?<\/\1\s*>/is', ' ', $result->body) ?? $result->body), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        )));
        foreach (self::MARKERS as $marker) {
            if (str_contains($visible, $marker)) {
                return true;
            }
        }

        $path = strtolower((string) parse_url($result->finalUrl ?? '', PHP_URL_PATH));

        return preg_match('~/(?:login|signin|sign-in)(?:/|$)~', $path) === 1;
    }
}
