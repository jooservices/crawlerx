<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Narrows loosely typed values to strings.
 */
trait CoercesStrings
{
    /** A trimmed non-empty string, or null for anything else. */
    protected function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
