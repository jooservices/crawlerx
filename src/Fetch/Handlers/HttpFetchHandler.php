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
use JOOservices\CrawlerX\Services\ClientFactory;
use Throwable;

final class HttpFetchHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
        private readonly ?SessionStore $sessions = null,
        private readonly ?LoginCookieProvider $logins = null,
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

        $http = $options?->http;
        $clientOptions = [
            'timeout' => $http !== null && $http->timeout !== null ? $http->timeout : $profile->http->timeout,
            'verify_ssl' => $http !== null && $http->verifySsl !== null ? $http->verifySsl : $profile->http->verifySsl,
            'headers' => $headers,
        ];

        try {
            $response = $this->clientFactory->factory($clientOptions, $profile->slug)->get($url);
            $psr = $response->toPsrResponse();
            $body = (string) $psr->getBody();
            $status = $psr->getStatusCode();
            /** @var array<string, list<string>> $headerMap */
            $headerMap = [];
            foreach ($psr->getHeaders() as $name => $values) {
                $headerMap[(string) $name] = array_values($values);
            }
        } catch (Throwable $exception) {
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
        $ok = ! $challenge && ChallengeDetector::isUsableBody($body, $status);

        return new FetchResultDto(
            ok: $ok,
            body: $body,
            status: $status,
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: $url,
            headers: $headerMap,
            error: $ok ? null : ($challenge ? 'challenge page' : 'unusable HTTP body'),
        );
    }
}
