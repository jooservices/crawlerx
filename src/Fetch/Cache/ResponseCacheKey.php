<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Cache;

use JOOservices\CrawlerX\Enums\FetchMethod;

final class ResponseCacheKey
{
    /** @param array<string, string> $headers */
    public static function for(FetchMethod $method, string $url, array $headers): string
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[] = [strtolower(trim($name)), trim($value)];
        }
        sort($normalized);

        return 'crawlerx.response.' . substr(hash('sha256', serialize([$method->value, $url, $normalized])), 0, 46);
    }
}
