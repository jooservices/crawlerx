<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

final class SizeParser
{
    public static function bytes(?string $text): ?int
    {
        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        $normalized = str_replace("\xc2\xa0", ' ', html_entity_decode($text));
        if (preg_match('/(?<![A-Z0-9])(\d+(?:[.,]\d+)?)\s*(B|KB|KIB|MB|MIB|GB|GIB|TB|TIB)\b/i', $normalized, $match) !== 1) {
            return null;
        }

        $multipliers = [
            'b' => 1,
            'kb' => 1024,
            'kib' => 1024,
            'mb' => 1024 ** 2,
            'mib' => 1024 ** 2,
            'gb' => 1024 ** 3,
            'gib' => 1024 ** 3,
            'tb' => 1024 ** 4,
            'tib' => 1024 ** 4,
        ];

        return (int) round((float) str_replace(',', '.', $match[1]) * $multipliers[strtolower($match[2])]);
    }
}
