<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Request;

use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;

/**
 * Caps the timeouts handed to a handler by what is left of the fetch budget.
 */
final class BudgetBounds
{
    public static function profile(SiteProfileDto $profile, FetchMethod $method, FetchBudget $budget): SiteProfileDto
    {
        if (
            $profile->playwright === null
            || ! in_array(
                $method,
                [
                    FetchMethod::Playwright,
                    FetchMethod::PlaywrightStealth,
                    FetchMethod::ChromeStealth,
                    FetchMethod::PuppeteerStealth,
                ],
                true,
            )
        ) {
            return $profile;
        }

        $playwright = $profile->playwright;
        $timeoutMs = min($playwright->navigationTimeoutMs, $budget->timeoutSeconds($method) * 1000);

        return new SiteProfileDto(
            slug: $profile->slug,
            displayName: $profile->displayName,
            baseUrl: $profile->baseUrl,
            fetchProfile: $profile->fetchProfile,
            fetchChain: $profile->fetchChain,
            http: $profile->http,
            playwright: new PlaywrightProfileDto(
                browser: $playwright->browser,
                headless: $playwright->headless,
                postWaitMs: min($playwright->postWaitMs, max(0, ($budget->timeoutSeconds($method) - 1) * 1000)),
                readyTimeoutMs: min($playwright->readyTimeoutMs, max(1, ($budget->timeoutSeconds($method) - 1) * 1000)),
                navigationTimeoutMs: $timeoutMs,
                stealthEnabled: $playwright->stealthEnabled,
                blockResources: $playwright->blockResources,
                viewport: $playwright->viewport,
                locale: $playwright->locale,
                timezoneId: $playwright->timezoneId,
                userAgent: $playwright->userAgent,
                storageStatePath: $playwright->storageStatePath,
                settleAfterReadyMs: $playwright->settleAfterReadyMs,
            ),
            cookieHandoffAfterBrowser: $profile->cookieHandoffAfterBrowser,
            readyMarkers: $profile->readyMarkers,
            soft404Markers: $profile->soft404Markers,
            defaultThrottle: $profile->defaultThrottle,
        );
    }

    /**
     * @param  list<string>  $readyMarkers
     * @param  array<string, mixed>|null  $sessionStorageState
     */
    public static function options(
        ?CrawlOptionsDto $options,
        FetchMethod $method,
        FetchBudget $budget,
        array $readyMarkers = [],
        ?array $sessionStorageState = null,
    ): CrawlOptionsDto {
        $http = $options?->http;
        $timeout = $budget->timeoutSeconds($method);
        if ($http?->timeout !== null) {
            $timeout = min($timeout, $http->timeout);
        }

        $httpOptions = in_array($method, [FetchMethod::Http, FetchMethod::CurlImpersonate, FetchMethod::Flaresolverr], true)
            ? new HttpOptionsDto(
                timeout: $timeout,
                verifySsl: $http?->verifySsl,
                headers: $http?->headers,
            )
            : $http;

        /** @var array<string, mixed>|null $storageState */
        $storageState = $options === null ? $sessionStorageState : ($options->storageState ?? $sessionStorageState);

        return new CrawlOptionsDto(
            http: $httpOptions,
            fetch: $options?->fetch,
            methodTimeoutSeconds: $budget->timeoutSeconds($method),
            storageState: $storageState,
            readyMarkers: $readyMarkers !== [] ? $readyMarkers : ($options !== null ? $options->readyMarkers : []),
        );
    }
}
