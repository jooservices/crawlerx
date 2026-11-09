<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fc2;

/**
 * Parses FC2 point prices such as "1,200 pt".
 */
trait ParsesPoints
{
    protected function points(?string $text): ?float
    {
        return $text !== null && preg_match('/([\d,]+)\s*pt/i', $text, $match) === 1
            ? (float) str_replace(',', '', $match[1])
            : null;
    }
}
