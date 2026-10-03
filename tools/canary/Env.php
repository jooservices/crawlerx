<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\Canary;

final class Env
{
    /**
     * @return array<string, string>
     */
    public static function load(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));
            if ($name === '') {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $values[$name] = $value;
        }

        return $values;
    }

    /** @param array<string, string> $values */
    public static function cookieForSite(string $site, array $values): ?string
    {
        $name = 'CRAWLERX_COOKIE_' . strtoupper(str_replace('-', '_', $site));
        $cookie = trim($values[$name] ?? '');

        return $cookie === '' ? null : $cookie;
    }
}
