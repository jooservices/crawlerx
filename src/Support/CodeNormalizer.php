<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

final class CodeNormalizer
{
    public static function canonical(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null || trim($value) === '') {
                continue;
            }

            $normalized = strtoupper(rawurldecode($value));
            $normalized = str_replace(['_', '+'], ' ', $normalized);

            if (preg_match('/FC2[\s-]*(?:PPV)?[\s-]*(\d{4,})/i', $normalized, $match) === 1) {
                return 'FC2-PPV-' . $match[1];
            }

            if (preg_match('/\b([A-Z]{2,10})[\s-]*(\d{2,6})\b/i', $normalized, $match) === 1) {
                return strtoupper($match[1]) . '-' . $match[2];
            }
        }

        return null;
    }
}
