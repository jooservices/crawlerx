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

        $cached = $this->cachedResponse($url, $profile, $method, $options);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->handler->fetch($url, $profile, $method, $options);
        if ($this->isCacheable($result)) {
            $this->store($url, $method, $this->handler->requestHeaders($url, $profile, $options), $result);
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

        $key = $this->key($method, $url, $this->handler->requestHeaders($url, $profile, $options));
        try {
            $entry = $this->cache->get($key);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($entry) || ! is_string($entry['body'] ?? null) || ! is_int($entry['status'] ?? null)) {
            return null;
        }

        $headers = $this->responseHeaders($entry['headers'] ?? null);
        $cookies = $this->cookies($entry['cookies'] ?? null);
        $finalUrl = $entry['finalUrl'] ?? null;
        if ($headers === null || $cookies === null || ($finalUrl !== null && ! is_string($finalUrl))) {
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
            && preg_match('/\b(?:captcha|recaptcha|hcaptcha|funcaptcha)\b/i', $result->body) !== 1;
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
            $this->cache->set($this->key($method, $url, $headers), $entry, $this->ttlSeconds);
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

        return 'crawlerx.response.' . substr(hash('sha256', serialize([$method->value, $url, $normalized])), 0, 46);
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
