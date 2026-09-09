<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Session;

final class CookieHandoffStore
{
    /** @var array<string, array<string, string>> */
    private array $cookies = [];

    /**
     * @param  array<string, string>  $cookies
     */
    public function put(string $host, array $cookies): void
    {
        if ($cookies === []) {
            return;
        }

        $this->cookies[$this->normalize($host)] = $cookies;
    }

    /**
     * @return array<string, string>
     */
    public function get(string $host): array
    {
        return $this->cookies[$this->normalize($host)] ?? [];
    }

    public function cookieHeader(string $host): ?string
    {
        $cookies = $this->get($host);
        if ($cookies === []) {
            return null;
        }

        $parts = [];
        foreach ($cookies as $name => $value) {
            $parts[] = $name . '=' . $value;
        }

        return implode('; ', $parts);
    }

    public function forget(string $host): void
    {
        unset($this->cookies[$this->normalize($host)]);
    }

    private function normalize(string $host): string
    {
        $host = strtolower(trim($host));
        $parsed = parse_url($host, PHP_URL_HOST);

        return is_string($parsed) && $parsed !== '' ? strtolower($parsed) : $host;
    }
}
