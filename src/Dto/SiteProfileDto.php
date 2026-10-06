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
        /** @var array<string, float>|null */
        public readonly ?array $defaultThrottle = null,
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
            defaultThrottle: self::normalizeThrottle($manifest->defaultThrottle),
        );
    }

    /** @param array<string, mixed>|null $settings
     *  @return array<string, float>|null
     */
    private static function normalizeThrottle(?array $settings): ?array
    {
        if ($settings === null) {
            return null;
        }

        $normalized = [];
        foreach (['default_gap_seconds', 'min_gap_seconds', 'max_gap_seconds'] as $key) {
            $value = $settings[$key] ?? null;
            if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
                $normalized[$key] = (float) $value;
            }
        }

        return $normalized === [] ? null : $normalized;
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
