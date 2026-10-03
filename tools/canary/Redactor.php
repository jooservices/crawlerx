<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\Canary;

final class Redactor
{
    /** @param list<string> $secrets */
    public static function text(string $value, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $value = str_replace($secret, '[REDACTED]', $value);
            }
        }

        return preg_replace(
            '/((?:remember_token|csrf(?:token)?|authenticity_token)[=:\\s]+)[^&;\\s"<]+/i',
            '$1[REDACTED]',
            $value,
        ) ?? '[REDACTED]';
    }
}
