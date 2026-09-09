<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use JOOservices\CrawlerX\Contracts\AdapterManifestRegistry;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerX\Exceptions\UnsupportedUrlException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchPlanResolver;

final class CrawlOrchestrator
{
    public function __construct(
        private readonly UrlClassifier $classifier,
        private readonly CrawlerXService $service,
        private readonly AdapterManifestRegistry $manifests,
        private readonly FetchPlanResolver $fetchPlanResolver,
        private readonly FetchFallbackChain $fetchChain,
    ) {
    }

    public function url(string $url): CrawlRequestBuilder
    {
        return new CrawlRequestBuilder($this, $url);
    }

    public function site(string $slug): CrawlRequestBuilder
    {
        return (new CrawlRequestBuilder($this, null))->site($slug);
    }

    public function crawl(
        ?string $url,
        ?string $site = null,
        ?CrawlType $type = null,
        ?int $page = null,
        ?CrawlOptionsDto $options = null,
    ): CrawlListResultDto|CrawlItemResultDto {
        if ($url === null || trim($url) === '') {
            throw new UnsupportedUrlException('');
        }

        $classified = $this->classifier->classify($url, $site, $type, $page);
        $manifest = $this->manifests->get($classified['slug']);
        if ($manifest === null) {
            throw new AdapterNotFoundException($classified['slug']);
        }

        $profile = SiteProfileDto::fromManifest($manifest);
        $plan = $this->fetchPlanResolver->resolve($profile, $options?->fetch);
        $fetch = $this->fetchChain->fetch($classified['url'], $profile, $plan, $options);

        $request = new CrawlRequestDto(
            url: $fetch->finalUrl ?? $classified['url'],
            type: $classified['type'],
            page: $classified['page'],
            options: $options,
            fetch: $fetch,
        );

        return $this->service->site($classified['slug'])->crawl($request);
    }
}
