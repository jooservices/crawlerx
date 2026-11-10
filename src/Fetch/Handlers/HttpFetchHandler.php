<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Handlers;

use Closure;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use JOOservices\CrawlerX\Fetch\Guard\TransientRetry;
use JOOservices\CrawlerX\Fetch\Request\SensitiveHeaders;
use JOOservices\CrawlerX\Fetch\ResponseBodyReader;
use JOOservices\CrawlerX\Fetch\ResponseBodyTooLargeException;
use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\Client\Exceptions\DownloadSizeExceededException;
use JOOservices\Client\Exceptions\RequestException;
use Psr\Http\Message\RequestInterface;
use Throwable;

final class HttpFetchHandler implements FetchMethodHandler
{
    /** @var Closure(RequestInterface): void|null */
    private readonly ?Closure $beforeRequest;

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
        private readonly ?SessionStore $sessions = null,
        private readonly ?LoginCookieProvider $logins = null,
        private readonly int $maxResponseBytes = 10_485_760,
        ?RobotsGuard $robotsGuard = null,
        private readonly ?string $defaultUserAgent = null,
        private readonly bool $defaultRespectRobots = true,
    ) {
        $this->beforeRequest = $robotsGuard === null
            ? null
            : static function (RequestInterface $request) use ($robotsGuard): void {
                $robotsGuard->assertAllowed((string) $request->getUri(), $request->getHeaderLine('User-Agent'));
            };
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
        $urlParts = parse_url($url);
        $urlHasCredentials = is_array($urlParts) && (isset($urlParts['user']) || isset($urlParts['pass']));
        $redirectsDisabled = $urlHasCredentials;
        $redirectOptions = ['track_redirects' => true];
        if ($redirectSensitiveHeaders !== []) {
            $redirectOptions['sensitive_headers'] = $redirectSensitiveHeaders;
        }
        $clientOptions = [
            'timeout' => $http !== null && $http->timeout !== null ? $http->timeout : $profile->http->timeout,
            'verify_ssl' => $http !== null && $http->verifySsl !== null ? $http->verifySsl : $profile->http->verifySsl,
            'headers' => $headers,
            'proxy' => $proxy,
            'max_response_bytes' => $this->maxResponseBytes,
            // A remote proxy resolves the redirect destination itself, so local DNS validation cannot pin its egress.
            'allow_redirects' => $redirectsDisabled ? false : $redirectOptions,
        ];
        $respectRobots = $profile->respectRobots ?? $this->defaultRespectRobots;
        if (($this->beforeRequest !== null && $respectRobots) || $proxy !== null || $redirectSensitiveHeaders !== []) {
            $origin = self::origin($url);
            $beforeRequest = $this->beforeRequest;
            $clientOptions['before_request'] = static function (RequestInterface $request) use (
                $beforeRequest,
                $respectRobots,
                $proxy,
                $origin,
                $redirectSensitiveHeaders,
            ): void {
                $requestOrigin = self::origin((string) $request->getUri());
                if ($proxy !== null && $requestOrigin !== $origin) {
                    throw new RequestException($request, 'A proxied redirect changed origin.');
                }

                if ($requestOrigin !== $origin) {
                    foreach ($redirectSensitiveHeaders as $header) {
                        if ($request->hasHeader($header)) {
                            throw new RequestException($request, 'A sensitive redirect changed origin.');
                        }
                    }
                }

                if ($respectRobots) {
                    $beforeRequest?->__invoke($request);
                }
            };
        }

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
            if ($exception instanceof RobotsDisallowedException) {
                throw $exception;
            }

            if ($exception instanceof ResponseBodyTooLargeException || $exception instanceof DownloadSizeExceededException) {
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
        $redirectNotFollowed = $status >= 300 && $status < 400 && $this->finalUrl($headerMap, $url) === $url;
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
        if (! $this->hasHeader($headers, 'User-Agent')) {
            $userAgent = $this->defaultUserAgent ?? $profile->playwright?->userAgent;
            if ($userAgent !== null && $userAgent !== '') {
                $headers['User-Agent'] = $userAgent;
            }
        }
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

    /** @param array<string, string> $headers */
    private function hasHeader(array $headers, string $expected): bool
    {
        foreach (array_keys($headers) as $name) {
            if (strcasecmp($name, $expected) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! is_string($parts['scheme'] ?? null) || ! is_string($parts['host'] ?? null)) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(rtrim($parts['host'], '.'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme . '://' . $host . ':' . $port;
    }
}
