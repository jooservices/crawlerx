<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Javbtc;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;

final class UrlNormalizer
{
    use NormalizesUrls {
        absolute as private baseAbsolute;
    }

    public function absolute(string $baseUrl, string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $this->baseAbsolute($baseUrl, $url);
    }
}
