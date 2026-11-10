<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Strips a product code from a title.
 */
trait StripsTitleCode
{
    /** Drops a leading product code (`ABC-123 Title` gives `Title`); keeps the title when nothing else would remain. */
    protected function titleWithoutCode(?string $title, string $code): ?string
    {
        if ($title === null) {
            return null;
        }

        $stripped = preg_replace('/^' . preg_quote($code, '/') . '\s*/i', '', $title);

        return is_string($stripped) && trim($stripped) !== '' ? trim($stripped) : $title;
    }
}
