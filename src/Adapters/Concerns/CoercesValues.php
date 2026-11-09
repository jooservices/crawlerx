<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Narrow loosely typed parsed values (JSON, attributes, scraped text) to the scalars DTOs expect.
 */
trait CoercesValues
{
    /** A trimmed non-empty string, or null for anything else. */
    protected function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** A strictly positive integer from an int or numeric string, or null. */
    protected function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Collapses whitespace runs and decodes HTML entities; always returns a string. */
    protected function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));
    }
}
