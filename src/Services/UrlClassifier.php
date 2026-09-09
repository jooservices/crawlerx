<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\AmbiguousUrlException;
use JOOservices\CrawlerX\Exceptions\UnsupportedUrlException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;

final class UrlClassifier
{
    public function __construct(private readonly AdapterRegistry $registry)
    {
    }

    /**
     * @return array{slug: string, type: CrawlType, page: int, url: string}
     */
    public function classify(string $url, ?string $siteOverride = null, ?CrawlType $typeOverride = null, ?int $pageOverride = null): array
    {
        $type = $typeOverride;
        $normalizedUrl = $url;

        if ($siteOverride !== null) {
            $adapter = $this->registry->resolve($siteOverride);
            if ($adapter instanceof UrlDetectCapable) {
                $result = $adapter->detectUrl($url);
                $normalizedUrl = $result->normalizedUrl ?? $url;
                if ($result->matched && $result->crawlType instanceof CrawlType) {
                    $type ??= $result->crawlType;
                }
            }

            $slug = $siteOverride;
        } else {
            $slug = null;
            foreach (array_keys($this->registry->all()) as $name) {
                $adapter = $this->registry->resolve($name);
                if (! $adapter instanceof UrlDetectCapable) {
                    continue;
                }

                $result = $adapter->detectUrl($url);
                if (! $result->hostMatched) {
                    continue;
                }

                $slug = $name;
                $normalizedUrl = $result->normalizedUrl ?? $url;
                if ($result->matched && $result->crawlType instanceof CrawlType) {
                    $type ??= $result->crawlType;
                }

                break;
            }
        }

        if ($slug === null) {
            throw new UnsupportedUrlException($url);
        }

        if ($type === null) {
            throw new AmbiguousUrlException($url);
        }

        $page = $pageOverride ?? $this->pageFromUrl($normalizedUrl);

        return [
            'slug' => $slug,
            'type' => $type,
            'page' => $page,
            'url' => $normalizedUrl,
        ];
    }

    private function pageFromUrl(string $url): int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return 1;
        }

        parse_str($query, $params);
        $page = $params['page'] ?? 1;

        return is_numeric($page) && (int) $page > 0 ? (int) $page : 1;
    }
}
