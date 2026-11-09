<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Cache;

use JOOservices\CrawlerX\Contracts\CacheAwareFetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Request\SensitiveHeaders;
use JOOservices\CrawlerX\Services\ClientFactory;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final class ResponseCache implements CacheAwareFetchMethodHandler
{
    public function __construct(
        private readonly HttpFetchHandler $handler,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === FetchMethod::Http && $this->handler->supports($method);
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        if ($method !== FetchMethod::Http || $this->ttlSeconds <= 0) {
            return $this->handler->fetch($url, $profile, $method, $options);
        }

        $headers = $this->handler->requestHeaders($url, $profile, $options);
        if (! $this->cacheableRequest($url, $headers, $profile, $options)) {
            return $this->handler->fetch($url, $profile, $method, $options);
        }

        $cached = $this->cachedResponse($url, $profile, $method, $options);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->handler->fetch($url, $profile, $method, $options);
        if ($result->finalUrl === $url && $this->isCacheable($result)) {
            $this->store($url, $method, $headers, $result);
        }

        return $result;
    }

    public function cachedResponse(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): ?FetchResultDto {
        if ($method !== FetchMethod::Http || $this->ttlSeconds <= 0) {
            return null;
        }

        $headers = $this->handler->requestHeaders($url, $profile, $options);
        if (! $this->cacheableRequest($url, $headers, $profile, $options)) {
            return null;
        }

        $key = $this->key($method, $url, $headers);
        try {
            $entry = $this->cache->get($key);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($entry)) {
            return null;
        }

        try {
            $entry = json_decode($entry, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($entry) || ! is_string($entry['body'] ?? null) || ! is_int($entry['status'] ?? null)) {
            return null;
        }

        $headers = $this->responseHeaders($entry['headers'] ?? null);
        $cookies = $this->cookies($entry['cookies'] ?? null);
        $finalUrl = $entry['finalUrl'] ?? null;
        if (
            $headers === null
            || $cookies === null
            || ($finalUrl !== null && ! is_string($finalUrl))
            || ($finalUrl !== null && $finalUrl !== $url)
        ) {
            return null;
        }

        $result = new FetchResultDto(
            ok: true,
            body: $entry['body'],
            status: $entry['status'],
            methodUsed: FetchMethod::Http,
            elapsedMs: 0,
            challengeDetected: false,
            finalUrl: $finalUrl,
            cookies: $cookies,
            headers: $headers,
        );

        return $this->isCacheable($result) ? $result : null;
    }

    private function isCacheable(FetchResultDto $result): bool
    {
        return $result->ok
            && $result->status >= 200
            && $result->status < 300
            && ! $result->challengeDetected
            && ! ChallengeDetector::isChallenge($result->body, $result->status, $result->headers)
            && $this->cacheableResponseHeaders($result)
            && preg_match('/\b(?:captcha|recaptcha|hcaptcha|funcaptcha)\b/i', $result->body) !== 1;
    }

    /** @param array<string, string> $headers */
    private function cacheableRequest(
        string $url,
        array $headers,
        SiteProfileDto $profile,
        ?CrawlOptionsDto $options,
    ): bool {
        $proxy = $options?->http?->proxy;
        if ($proxy === null || trim($proxy) === '') {
            $proxy = $profile->http->proxy;
        }
        if (ClientFactory::resolveProxy(['proxy' => $proxy]) !== null) {
            return false;
        }

        $parts = parse_url($url);
        if (is_array($parts) && (isset($parts['user']) || isset($parts['pass']))) {
            return false;
        }

        $query = is_array($parts) ? ($parts['query'] ?? null) : null;
        if (is_string($query)) {
            foreach (explode('&', $query) as $parameter) {
                $name = rawurldecode(explode('=', $parameter, 2)[0]);
                if (SensitiveHeaders::isSensitiveName($name)) {
                    return false;
                }
            }
        }

        if (SensitiveHeaders::contains($headers)) {
            return false;
        }

        return true;
    }

    private function cacheableResponseHeaders(FetchResultDto $result): bool
    {
        if ($result->cookies !== []) {
            return false;
        }

        foreach ($result->headers as $name => $values) {
            if (strcasecmp($name, 'Set-Cookie') === 0 || SensitiveHeaders::isSensitiveName($name)) {
                return false;
            }

            if (strcasecmp($name, 'Cache-Control') === 0 && preg_match('/(?:^|,)\s*(?:private|no-store)\b/i', implode(',', $values)) === 1) {
                return false;
            }

            if (strcasecmp($name, 'Vary') === 0) {
                $varyTokens = array_map('trim', explode(',', implode(',', $values)));
                if (in_array('*', $varyTokens, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, string> $headers */
    private function store(string $url, FetchMethod $method, array $headers, FetchResultDto $result): void
    {
        $entry = [
            'body' => $result->body,
            'status' => $result->status,
            'finalUrl' => $result->finalUrl,
            'headers' => $result->headers,
            'cookies' => $result->cookies,
        ];

        try {
            $this->cache->set(
                $this->key($method, $url, $headers),
                json_encode($entry, JSON_THROW_ON_ERROR),
                $this->ttlSeconds,
            );
        } catch (Throwable) {
            // A cache outage must not turn a successful HTTP fetch into a failure.
        }
    }

    /** @param array<string, string> $headers */
    private function key(FetchMethod $method, string $url, array $headers): string
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[] = [strtolower(trim($name)), trim($value)];
        }
        sort($normalized);

        $parts = [$method->value, $url];
        foreach ($normalized as [$name, $value]) {
            $parts[] = strlen($name) . ':' . $name . strlen($value) . ':' . $value;
        }
        $material = '';
        foreach ($parts as $part) {
            $material .= strlen($part) . ':' . $part;
        }

        return 'crawlerx.response.' . substr(hash('sha256', $material), 0, 46);
    }

    /** @return array<string, list<string>>|null */
    private function responseHeaders(mixed $headers): ?array
    {
        if (! is_array($headers)) {
            return null;
        }

        $validated = [];
        foreach ($headers as $name => $values) {
            if (! is_string($name) || ! is_array($values)) {
                return null;
            }
            foreach ($values as $value) {
                if (! is_string($value)) {
                    return null;
                }
            }
            $validated[$name] = array_values($values);
        }

        return $validated;
    }

    /** @return array<string, string>|null */
    private function cookies(mixed $cookies): ?array
    {
        if (! is_array($cookies)) {
            return null;
        }

        foreach ($cookies as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                return null;
            }
        }

        return $cookies;
    }
}
