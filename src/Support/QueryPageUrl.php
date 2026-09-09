<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

final class QueryPageUrl
{
    public static function build(string $url, int $page): string
    {
        if ($page === 1) {
            $updated = preg_replace('/([?&])page=\d+&?/', '$1', $url) ?? $url;

            return rtrim($updated, '?&');
        }

        if (preg_match('/([?&])page=\d+/', $url) === 1) {
            return preg_replace('/([?&]page=)\d+/', '${1}' . $page, $url) ?? $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'page=' . $page;
    }
}
