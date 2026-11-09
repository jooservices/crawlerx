<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avjoho;

/**
 * Avjoho performer slugs live at the site root; "sitemap" is not a performer.
 */
trait ParsesAvjohoIds
{
    abstract protected function pathMatch(string $url, string $pattern): ?string;

    protected function externalId(string $url): ?string
    {
        $slug = $this->pathMatch($url, '#^/([^/]+)/?$#');

        return $slug !== null && $slug !== 'sitemap' ? $slug : null;
    }
}
