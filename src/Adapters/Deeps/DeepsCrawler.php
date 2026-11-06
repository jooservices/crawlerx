<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Deeps;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Deeps\Types\Detail;
use JOOservices\CrawlerX\Adapters\Deeps\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\UrlDetectionResult;

final class DeepsCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls {
        detectUrl as private detectBaseUrl;
    }
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    protected array $options = [
        'base_uri' => Site::BASE_URL,
    ];

    public function name(): string
    {
        return 'deeps';
    }

    /**
     * The product code is a key-less query (`detail.php?mmgo-020`). The shared import normalizer rewrites it to
     * `?mmgo-020=`, which the site serves as a different page, so a detail URL is kept exactly as given.
     */
    public function detectUrl(string $url): UrlDetectionResult
    {
        $result = $this->detectBaseUrl($url);

        return $result->matched && $result->normalizedUrl !== null
            ? new UrlDetectionResult(
                hostMatched: $result->hostMatched,
                matched: true,
                crawlType: $result->crawlType,
                entity: $result->entity,
                urlType: $result->urlType,
                normalizedUrl: trim($url),
            )
            : $result;
    }
}
