<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avjoho;

use JOOservices\CrawlerX\Adapters\Concerns\MatchesUrlPath;

/**
 * Avjoho performer slugs live at the site root; "sitemap" is not a performer.
 */
trait ParsesAvjohoIds
{
    use MatchesUrlPath;

    protected function externalId(string $url): ?string
    {
        $slug = $this->pathMatch($url, '#^/([^/]+)/?$#');

        return $slug !== null && $slug !== 'sitemap' ? $slug : null;
    }
}
