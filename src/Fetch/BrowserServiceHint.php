<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

/**
 * Turns the raw failure of a local Node browser script into a configuration message when the cause is a missing
 * browser setup, so a missing PLAYWRIGHT_URL does not surface as a Node stack trace.
 */
final class BrowserServiceHint
{
    private const MESSAGE = 'Browser service is not configured: set PLAYWRIGHT_URL to a CrawlerX browser service, or install Node.js and the playwright package for local runs.';

    public static function describe(string $stderr, string $fallback): string
    {
        if ($stderr === '') {
            return $fallback;
        }

        foreach (['ERR_MODULE_NOT_FOUND', 'Cannot find package', 'Cannot find module', 'node: not found', 'node: command not found'] as $marker) {
            if (str_contains($stderr, $marker)) {
                return self::MESSAGE;
            }
        }

        return $stderr;
    }
}
