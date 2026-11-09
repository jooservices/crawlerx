<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Reads an identifier from the URL path, falling back to the query string.
 */
trait ReadsPathOrQueryParam
{
    use MatchesUrlPath;
    use ReadsQueryParams;

    /** A captured path value, falling back to a trimmed scalar query value. */
    protected function pathMatchOrQueryParam(string $url, string $pattern, string $name): ?string
    {
        $match = $this->pathMatch($url, $pattern);
        if ($match !== null) {
            return $match;
        }

        $value = $this->queryParam($url, $name);

        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }
}
