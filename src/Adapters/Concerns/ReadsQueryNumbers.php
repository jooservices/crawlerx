<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Reads page numbers and counters from query-string parameters.
 */
trait ReadsQueryNumbers
{
    use ReadsQueryParams;

    /** A positive page number from a query-string parameter, or null. */
    protected function queryNumber(string $url, string $name): ?int
    {
        $value = $this->queryParam($url, $name);

        return $value !== null && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** A positive integer from a numeric query value, preserving PHP's numeric-string coercion. */
    protected function positiveNumericQueryNumber(string $url, string $name): ?int
    {
        $value = $this->queryParam($url, $name);

        return $value !== null && is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** A page number from a numeric query value, clamped to the first page. */
    protected function queryPageNumber(string $url, string $name): ?int
    {
        $value = $this->queryParam($url, $name);

        return $value !== null && is_numeric($value) ? max(1, (int) $value) : null;
    }
}
