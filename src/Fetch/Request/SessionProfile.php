<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Request;

use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;

/**
 * Merges session cookies, login cookies and the request User-Agent into a site profile.
 */
final class SessionProfile
{
    /**
     * @param  array{cookies: array<string, string>, userAgent: ?string, source: string, expiresAt: int, storageState: array<string, mixed>|null, challengeCount: int}|null  $session
     * @param  array<string, string>  $loginCookies
     */
    public static function apply(
        SiteProfileDto $profile,
        ?CrawlOptionsDto $options,
        ?array $session,
        array $loginCookies,
        ?string $requestUserAgent,
    ): SiteProfileDto {
        $headers = $profile->http->headers;
        if ($options?->http?->headers !== null) {
            $headers = array_merge($headers, $options->http->headers);
        }

        $sessionCookies = $session['cookies'] ?? [];
        $cookies = array_merge(self::cookieHeaderToMap($headers['Cookie'] ?? null), $sessionCookies, $loginCookies);
        if ($cookies !== []) {
            $headers['Cookie'] = self::cookieMapToHeader($cookies);
        }

        $userAgent = $session['userAgent'] ?? $requestUserAgent;
        if ($userAgent !== null && $userAgent !== '') {
            $headers['User-Agent'] = $userAgent;
        }

        $playwright = $profile->playwright;
        if ($playwright !== null && $userAgent !== null && $userAgent !== '') {
            $playwright = new PlaywrightProfileDto(
                browser: $playwright->browser,
                headless: $playwright->headless,
                postWaitMs: $playwright->postWaitMs,
                navigationTimeoutMs: $playwright->navigationTimeoutMs,
                stealthEnabled: $playwright->stealthEnabled,
                viewport: $playwright->viewport,
                locale: $playwright->locale,
                timezoneId: $playwright->timezoneId,
                userAgent: $userAgent,
                storageStatePath: $playwright->storageStatePath,
                readyTimeoutMs: $playwright->readyTimeoutMs,
                blockResources: $playwright->blockResources,
                settleAfterReadyMs: $playwright->settleAfterReadyMs,
            );
        }

        return new SiteProfileDto(
            slug: $profile->slug,
            displayName: $profile->displayName,
            baseUrl: $profile->baseUrl,
            fetchProfile: $profile->fetchProfile,
            fetchChain: $profile->fetchChain,
            http: new HttpProfileDto($profile->http->timeout, $profile->http->verifySsl, $headers, $profile->http->proxy),
            playwright: $playwright,
            cookieHandoffAfterBrowser: $profile->cookieHandoffAfterBrowser,
            readyMarkers: $profile->readyMarkers,
            soft404Markers: $profile->soft404Markers,
            defaultThrottle: $profile->defaultThrottle,
        );
    }

    /** @return array<string, string> */
    private static function cookieHeaderToMap(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $cookies = [];
        foreach (explode(';', $header) as $part) {
            $separator = strpos($part, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($part, 0, $separator));
            $value = trim(substr($part, $separator + 1));
            if ($name !== '' && $value !== '') {
                $cookies[$name] = $value;
            }
        }

        return $cookies;
    }

    /** @param array<string, string> $cookies */
    private static function cookieMapToHeader(array $cookies): string
    {
        return implode('; ', array_map(
            static fn(string $name, string $value): string => $name . '=' . $value,
            array_keys($cookies),
            array_values($cookies),
        ));
    }
}
