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

    /** Removes a trailing phrase before applying the standard text cleanup. */
    protected function cleanTextWithoutTrailingPhrase(string $value, string $phrase): string
    {
        $pattern = '/\s*' . preg_quote($phrase, '/') . '\s*$/iu';
        $value = preg_replace($pattern, '', $value) ?? $value;

        return $this->cleanText($value);
    }

    /** A page number read from digit-only text, clamped to the first page. */
    protected function pageNumberFromText(?string $text): ?int
    {
        return $text !== null && ctype_digit($text) ? max(1, (int) $text) : null;
    }

    /** Drops a leading product code (`ABC-123 Title` gives `Title`); keeps the title when nothing else would remain. */
    protected function titleWithoutCode(?string $title, string $code): ?string
    {
        if ($title === null) {
            return null;
        }

        $stripped = preg_replace('/^' . preg_quote($code, '/') . '\s*/i', '', $title);

        return is_string($stripped) && trim($stripped) !== '' ? trim($stripped) : $title;
    }

    /** A view/like counter such as `1,234` or `1 234` as an int; 0 when it is not numeric. */
    protected function integerStat(string $value): int
    {
        $normalized = str_replace([',', ' '], '', $value);

        return is_numeric($normalized) ? (int) $normalized : 0;
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
