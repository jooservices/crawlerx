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

    /** Digits-only text as a page number floored at 1, or null. */
    protected function digitPage(?string $text): ?int
    {
        return $text !== null && ctype_digit($text) ? max(1, (int) $text) : null;
    }

    /** YYYY-MM-DD from a parseable date string, or null when empty or unparseable. */
    protected function dateString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = date_create_immutable(trim($value));

        return $date === false ? null : $date->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        /** @var array<string, mixed> $array */
        $array = is_array($value) ? $value : [];

        return $array;
    }
}
