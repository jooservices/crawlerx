<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Normalises scraped date strings.
 */
trait ParsesDateStrings
{
    /** YYYY-MM-DD from a parseable date string, or null when empty or unparseable. */
    protected function dateString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = date_create_immutable(trim($value));

        return $date === false ? null : $date->format('Y-m-d');
    }
}
