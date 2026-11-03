<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\AvWikiUrlDetectRules;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Adapters\AvWiki\Types\Detail;
use JOOservices\CrawlerX\Adapters\AvWiki\Types\Listing;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class AvWikiCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use AvWikiUrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'avwiki';
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveDefaultOptions(?AdapterManifestDto $manifest): array
    {
        $options = parent::resolveDefaultOptions($manifest);
        $userAgent = FetchRuntimeConfig::fromEnvironment()->userAgent;
        if (is_string($userAgent) && trim($userAgent) !== '') {
            $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
            $headers['User-Agent'] = $userAgent;
            $options['headers'] = $headers;
        }

        return $options;
    }
}
