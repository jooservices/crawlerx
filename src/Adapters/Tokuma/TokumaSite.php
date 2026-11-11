<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tokuma;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Support\MovieMeta;

final class TokumaSite
{
    use NormalizesUrls {
        absolute as private resolveAbsolute;
    }

    public const BASE_URL = 'https://www.tokuma.jp';

    public static function isPlaceholderImage(?string $url): bool
    {
        return $url !== null && str_contains((string) parse_url($url, PHP_URL_PATH), '/images/common/now_printing_150x187.gif');
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    public static function price(?string $label): ?array
    {
        if ($label === null || preg_match('/([\d,]+)\s*円/u', $label, $match) !== 1) {
            return null;
        }

        return MovieMeta::price((float) str_replace(',', '', $match[1]), 'JPY', $label);
    }

    public static function absolute(string $baseUrl, string $url): string
    {
        $site = new self();
        $absolute = $site->absoluteUrl($baseUrl, $url);

        return preg_replace('#(?<!:)//+#', '/', $absolute) ?? $absolute;
    }

    public static function detailUrl(string $externalId): string
    {
        return self::BASE_URL . '/book/' . $externalId . '.html';
    }

    public static function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/book/(b\d+)\.html$#', $path, $match) === 1 ? $match[1] : null;
    }

    private function absoluteUrl(string $baseUrl, string $url): string
    {
        return $this->resolveAbsolute($baseUrl, $url);
    }
}
