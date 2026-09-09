<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX;

use JOOservices\CrawlerX\Services\CrawlRequestBuilder;

final class CrawlerX
{
    public static function url(string $url): CrawlRequestBuilder
    {
        return CrawlerXFactory::create()->url($url);
    }

    public static function site(string $slug): CrawlRequestBuilder
    {
        return CrawlerXFactory::create()->site($slug);
    }
}
