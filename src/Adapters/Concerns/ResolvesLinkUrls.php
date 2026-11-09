<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Resolves a CSS-selected href to an absolute URL.
 *
 * Compose with ParsesHtml (firstAttribute) and NormalizesUrls (absolute).
 */
trait ResolvesLinkUrls
{
    abstract protected function firstAttribute(Crawler $crawler, string $selector, string $attribute): ?string;

    abstract protected function absolute(string $baseUrl, string $url): string;

    protected function linkUrl(Crawler $crawler, string $selector, string $baseUrl): ?string
    {
        $href = $this->firstAttribute($crawler, $selector, 'href');

        return $href === null ? null : $this->absolute($baseUrl, $href);
    }
}
