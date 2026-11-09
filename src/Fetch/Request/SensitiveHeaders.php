<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Request;

final class SensitiveHeaders
{
    /** @param array<string, string> $headers */
    public static function contains(array $headers): bool
    {
        foreach (array_keys($headers) as $name) {
            if (self::isSensitiveName($name)) {
                return true;
            }
        }

        return false;
    }

    public static function isSensitiveName(string $name): bool
    {
        return preg_match('/authorization|cookie|token|api[_-]?key|access[_-]?key|private[_-]?key|client[_-]?key|(?:^|[-_])key(?:$|[-_])|pass(?:word)?|secret|sig(?:nature)?|jwt|session|sid|credential|clearance|(?:^|[-_])auth(?:$|[-_])/i', $name) === 1;
    }
}
