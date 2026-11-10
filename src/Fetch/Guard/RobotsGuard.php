<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

use Closure;
use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final class RobotsGuard
{
    private const DEFAULT_TTL_SECONDS = 3600;

    /** @var array<string, RobotsTxt> */
    private array $memory = [];

    /** @var Closure(string): void */
    private readonly Closure $logger;

    /**
     * @param  Closure(string): ?string  $fetchRobotsTxt  returns the robots.txt body or null when it cannot be fetched
     * @param  Closure(string): void|null  $logger
     */
    public function __construct(
        private readonly Closure $fetchRobotsTxt,
        private readonly ?CacheInterface $cache = null,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        ?Closure $logger = null,
    ) {
        $this->logger = $logger ?? static function (string $message): void {
        };
    }

    /**
     * @throws RobotsDisallowedException when an explicit Disallow rule blocks the URL
     */
    public function assertAllowed(string $url, string $userAgent): void
    {
        $rule = $this->robotsTxtFor($url)->blockingRule($this->pathOf($url), $userAgent);

        if ($rule !== null) {
            throw new RobotsDisallowedException($url, $rule->pattern);
        }
    }

    public function crawlDelaySeconds(string $url, string $userAgent): ?float
    {
        return $this->robotsTxtFor($url)->crawlDelay($userAgent);
    }

    private function robotsTxtFor(string $url): RobotsTxt
    {
        $origin = $this->originOf($url);
        if ($origin === null) {
            return new RobotsTxt();
        }

        $originKey = $origin['scheme'] . '://' . $origin['host'] . ':' . $origin['port'];
        if (array_key_exists($originKey, $this->memory)) {
            return $this->memory[$originKey];
        }

        $key = $this->cacheKey($originKey);
        $content = $this->readCache($key);
        if ($content === null) {
            $content = ($this->fetchRobotsTxt)($this->robotsUrl($origin));

            if ($content === null) {
                // An unreachable robots.txt is not cached, so the next fetch of the host tries again.
                ($this->logger)("robots.txt is unreachable for host [{$origin['host']}]; failing open.");
                $content = '';
            } else {
                $this->writeCache($key, $content);
            }
        }

        $robots = $content === '' ? new RobotsTxt() : RobotsTxtParser::parse($content);
        $this->memory[$originKey] = $robots;

        return $robots;
    }

    private function readCache(string $key): ?string
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $value = $this->cache->get($key);
        } catch (Throwable) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    private function writeCache(string $key, string $content): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $this->cache->set($key, $content, $this->ttlSeconds);
        } catch (Throwable) {
            // Failing open also covers a cache write failure.
        }
    }

    /** @param array{scheme: string, host: string, port: int} $origin */
    private function robotsUrl(array $origin): string
    {
        $host = str_contains($origin['host'], ':') ? '[' . $origin['host'] . ']' : $origin['host'];
        $defaultPort = $origin['scheme'] === 'https' ? 443 : 80;
        $port = $origin['port'] === $defaultPort ? '' : ':' . $origin['port'];

        return $origin['scheme'] . '://' . $host . $port . '/robots.txt';
    }

    /** @return array{scheme: string, host: string, port: int}|null */
    private function originOf(string $url): ?array
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $scheme = is_string($scheme) && strtolower($scheme) === 'http' ? 'http' : 'https';
        $host = strtolower(rtrim(trim($host, '[]'), '.'));
        $explicitPort = parse_url($url, PHP_URL_PORT);
        $port = is_int($explicitPort) ? $explicitPort : ($scheme === 'https' ? 443 : 80);

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port];
    }

    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private function cacheKey(string $origin): string
    {
        return 'crawlerx.robots.' . substr(hash('sha256', $origin), 0, 50);
    }
}
