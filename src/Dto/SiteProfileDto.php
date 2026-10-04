<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\Dto\Core\Dto;

final class SiteProfileDto extends Dto
{
    /**
     * @param  list<FetchMethod>  $fetchChain
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $baseUrl,
        public readonly FetchProfile $fetchProfile,
        public readonly array $fetchChain,
        public readonly HttpProfileDto $http,
        public readonly ?PlaywrightProfileDto $playwright = null,
        public readonly bool $cookieHandoffAfterBrowser = false,
        /** @var array<string, list<string>> */
        public readonly array $readyMarkers = [],
        /** @var list<string> */
        public readonly array $soft404Markers = [],
    ) {
    }

    public static function fromManifest(AdapterManifestDto $manifest): self
    {
        $fetchProfile = $manifest->fetchProfile
            ?? ($manifest->playwrightFetchEnabled ? FetchProfile::BrowserLikely : FetchProfile::Adaptive);

        $headers = $manifest->browserHeaders ?? [];
        $timeout = $manifest->defaultCrawlConfig['timeout'] ?? 30;
        $timeoutSeconds = is_numeric($timeout) ? (int) $timeout : 30;

        return new self(
            slug: $manifest->slug,
            displayName: $manifest->displayName,
            baseUrl: $manifest->baseUrl,
            fetchProfile: $fetchProfile,
            fetchChain: $fetchProfile->chain(),
            http: new HttpProfileDto(
                timeout: $timeoutSeconds > 0 ? $timeoutSeconds : 30,
                verifySsl: true,
                headers: $headers,
            ),
            playwright: $fetchProfile === FetchProfile::HttpOnly
                ? null
                : new PlaywrightProfileDto(
                    userAgent: $headers['User-Agent'] ?? null,
                ),
            cookieHandoffAfterBrowser: $fetchProfile === FetchProfile::BrowserLikely,
            readyMarkers: $manifest->readyMarkers,
            soft404Markers: $manifest->soft404Markers,
        );
    }

    /** @return list<string> */
    public function readyMarkersFor(?CrawlType $type): array
    {
        if ($type === null) {
            if ($this->readyMarkers === []) {
                return [];
            }

            return array_values(array_unique(array_merge(...array_values($this->readyMarkers))));
        }

        return $this->readyMarkers[$type->value] ?? [];
    }
}
