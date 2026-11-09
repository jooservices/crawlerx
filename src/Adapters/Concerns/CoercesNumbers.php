<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Narrows loosely typed values and scraped text to numbers.
 */
trait CoercesNumbers
{
    /** A strictly positive integer from an int or numeric string, or null. */
    protected function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** A page number read from digit-only text, clamped to the first page. */
    protected function pageNumberFromText(?string $text): ?int
    {
        return $text !== null && ctype_digit($text) ? max(1, (int) $text) : null;
    }

    /** A view/like counter such as `1,234` or `1 234` as an int; 0 when it is not numeric. */
    protected function integerStat(string $value): int
    {
        $normalized = str_replace([',', ' '], '', $value);

        return is_numeric($normalized) ? (int) $normalized : 0;
    }
}
