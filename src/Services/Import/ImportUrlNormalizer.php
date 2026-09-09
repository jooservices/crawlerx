<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services\Import;

use InvalidArgumentException;

final class ImportUrlNormalizer
{
    /**
     * @return array{url: string, current_page: int}
     */
    public function normalize(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('Import URL cannot be empty.');
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Import URL must be absolute.');
        }

        $page = 1;
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';

        $queryValue = $parts['query'] ?? null;
        if (is_string($queryValue) && $queryValue !== '') {
            $parsed = [];
            parse_str($queryValue, $parsed);
            if (isset($parsed['page']) && is_numeric($parsed['page'])) {
                $page = max(1, (int) $parsed['page']);
                unset($parsed['page']);
            }

            $parts['query'] = http_build_query($parsed);
            if ($parts['query'] === '') {
                unset($parts['query']);
            }
        }

        if (preg_match('#/page/(\d+)/?$#', $path, $matches) === 1) {
            $page = max(1, (int) $matches[1]);
            $path = preg_replace('#/page/\d+/?$#', '', $path) ?? '';
        }

        $canonical = $this->buildBaseUrl($parts, $path);

        return [
            'url' => $canonical,
            'current_page' => $page,
        ];
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function buildBaseUrl(array $parts, string $path): string
    {
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';
        $port = isset($parts['port']) && is_int($parts['port']) ? ':' . $parts['port'] : '';
        $user = isset($parts['user']) && is_string($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) && is_string($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $queryString = is_string($parts['query'] ?? null) ? $parts['query'] : '';
        $query = $queryString !== '' ? '?' . $queryString : '';

        return "{$scheme}://{$auth}{$host}{$port}{$path}{$query}";
    }
}
