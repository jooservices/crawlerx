<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Handlers;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use JOOservices\CrawlerX\Fetch\Guard\TransientRetry;
use JOOservices\CrawlerX\Fetch\Request\SensitiveHeaders;
use JOOservices\CrawlerX\Fetch\ResponseBodyReader;
use JOOservices\CrawlerX\Fetch\ResponseBodyTooLargeException;
use JOOservices\CrawlerX\Services\ClientFactory;
use Throwable;

final class HttpFetchHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
        private readonly ?SessionStore $sessions = null,
        private readonly ?LoginCookieProvider $logins = null,
        private readonly int $maxResponseBytes = 10_485_760,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === FetchMethod::Http;
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        $started = (int) round(microtime(true) * 1000);
        $headers = $this->requestHeaders($url, $profile, $options);

        $http = $options?->http;
        $proxy = ClientFactory::resolveProxy([
            'proxy' => $this->proxy($http?->proxy, $profile->http->proxy),
        ]);
        $redirectSensitiveHeaders = array_keys(array_filter(
            $headers,
            static fn(string $name): bool => SensitiveHeaders::isSensitiveName($name),
            ARRAY_FILTER_USE_KEY,
        ));
        $nonCookieSensitiveHeaders = array_filter(
            $redirectSensitiveHeaders,
            static fn(string $name): bool => ! in_array(strtolower($name), ['cookie', 'cookie2'], true),
        );
        $urlParts = parse_url($url);
        $urlHasCredentials = is_array($urlParts) && (isset($urlParts['user']) || isset($urlParts['pass']));
        $redirectsDisabled = $proxy !== null || $nonCookieSensitiveHeaders !== [] || $urlHasCredentials;
        $redirectOptions = ['track_redirects' => true];
        if ($redirectSensitiveHeaders !== []) {
            $redirectOptions['sensitive_headers'] = $redirectSensitiveHeaders;
        }
        $clientOptions = [
            'timeout' => $http !== null && $http->timeout !== null ? $http->timeout : $profile->http->timeout,
            'verify_ssl' => $http !== null && $http->verifySsl !== null ? $http->verifySsl : $profile->http->verifySsl,
            'headers' => $headers,
            'proxy' => $proxy,
            // A remote proxy resolves the redirect destination itself, so local DNS validation cannot pin its egress.
            'allow_redirects' => $redirectsDisabled ? false : $redirectOptions,
        ];

        try {
            $response = $this->clientFactory->factory($clientOptions, $profile->slug)->get($url);
            $psr = $response->toPsrResponse();
            $body = (new ResponseBodyReader($this->maxResponseBytes))->read($psr->getBody());
            $status = $psr->getStatusCode();
            /** @var array<string, list<string>> $headerMap */
            $headerMap = [];
            foreach ($psr->getHeaders() as $name => $values) {
                $headerMap[(string) $name] = array_values($values);
            }
        } catch (Throwable $exception) {
            if ($exception instanceof ResponseBodyTooLargeException) {
                return new FetchResultDto(
                    ok: false,
                    body: '',
                    status: 0,
                    methodUsed: $method,
                    elapsedMs: (int) round(microtime(true) * 1000) - $started,
                    challengeDetected: false,
                    finalUrl: $url,
                    error: 'response_too_large',
                );
            }

            $error = TransientRetry::errorCode($exception);

            return new FetchResultDto(
                ok: false,
                body: '',
                status: 0,
                methodUsed: $method,
                elapsedMs: (int) round(microtime(true) * 1000) - $started,
                challengeDetected: false,
                finalUrl: $url,
                error: $error,
            );
        }

        $challenge = ChallengeDetector::isChallenge($body, $status, $headerMap);
        $redirectNotFollowed = $redirectsDisabled && $status >= 300 && $status < 400;
        $ok = ! $redirectNotFollowed && ! $challenge && ChallengeDetector::isUsableBody($body, $status);

        return new FetchResultDto(
            ok: $ok,
            body: $body,
            status: $status,
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: $this->finalUrl($headerMap, $url),
            headers: $headerMap,
            error: $ok ? null : ($redirectNotFollowed ? 'redirect_not_followed' : ($challenge ? 'challenge page' : 'unusable HTTP body')),
        );
    }

    /** @param array<string, list<string>> $headers */
    private function finalUrl(array $headers, string $fallback): string
    {
        foreach ($headers as $name => $values) {
            if (strcasecmp($name, 'X-Joo-Effective-Uri') === 0 && is_string($values[0] ?? null)) {
                return $values[0];
            }
        }

        return $fallback;
    }

    /** The first non-blank proxy of the request option and the site profile; blank values count as absent. */
    private function proxy(?string $requestProxy, ?string $profileProxy): ?string
    {
        foreach ([$requestProxy, $profileProxy] as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function requestHeaders(string $url, SiteProfileDto $profile, ?CrawlOptionsDto $options = null): array
    {
        $headers = $profile->http->headers;
        $cookie = $this->cookies->cookieHeader($url);
        if ($cookie !== null) {
            $headers['Cookie'] = $cookie;
        }

        if ($options?->http?->headers !== null) {
            $headers = array_merge($headers, $options->http->headers);
        }

        $loginCookies = $this->logins?->cookiesFor($profile->slug) ?? [];
        $sessionCookie = $this->sessions?->cookieHeader($profile->slug, $loginCookies);
        if ($sessionCookie !== null) {
            $headers['Cookie'] = $sessionCookie;
        }
        $sessionUserAgent = $this->sessions?->userAgent($profile->slug);
        if ($sessionUserAgent !== null && $sessionUserAgent !== '') {
            $headers['User-Agent'] = $sessionUserAgent;
        }

        return $headers;
    }
}
