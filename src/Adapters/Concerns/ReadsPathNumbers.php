<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Reads numbers captured from a URL path.
 */
trait ReadsPathNumbers
{
    use MatchesUrlPath;

    /** First capture group of $pattern matched against the URL path as a positive page number. */
    protected function pathNumber(string $url, string $pattern): ?int
    {
        $number = $this->pathMatch($url, $pattern);

        return $number !== null && (int) $number > 0 ? (int) $number : null;
    }

    /** Casts the first captured path value to an integer without imposing a positivity rule. */
    protected function pathInteger(string $url, string $pattern): ?int
    {
        $number = $this->pathMatch($url, $pattern);

        return $number !== null ? (int) $number : null;
    }
}
