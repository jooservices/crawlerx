<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Cleans scraped text.
 */
trait CleansText
{
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
}
