<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity;

use Symfony\Component\DomCrawler\UriResolver;

final class UrlNormalizer
{
    public function absolute(string $baseUrl, string $url): string
    {
        return UriResolver::resolve($url, $baseUrl);
    }
}
