<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\ManifestConsistency;

use JsonException;
use RuntimeException;

final class ManifestChecker
{
    private const SLUG_PATTERN = '/\A[a-z0-9][a-z0-9_-]*\z/';

    /**
     * @var list<string>
     */
    private const REQUIRED_KEYS = [
        'slug',
        'name',
        'version',
        'description',
        'author',
        'requires',
        'adapter',
        'base_url',
        'pagination',
        'runtime',
        'type',
    ];

    public function __construct(private readonly string $adaptersDirectory)
    {
    }

    /**
     * @return list<string>
     */
    public function check(): array
    {
        if (! is_dir($this->adaptersDirectory) || ! is_readable($this->adaptersDirectory)) {
            throw new RuntimeException('Adapters directory is missing or unreadable.');
        }

        $pattern = rtrim($this->adaptersDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifestPaths = glob($pattern, GLOB_NOSORT);
        if ($manifestPaths === false) {
            throw new RuntimeException('Could not list adapter manifests.');
        }

        sort($manifestPaths, SORT_STRING);

        $problems = [];
        $seenSlugs = [];
        foreach ($manifestPaths as $manifestPath) {
            $manifest = $this->readManifest($manifestPath);
            $slug = $manifest['slug'] ?? null;
            $identifier = $this->identifier($slug, $manifestPath);

            foreach (self::REQUIRED_KEYS as $key) {
                if (! array_key_exists($key, $manifest)) {
                    $problems[] = sprintf('MISSING_KEY %s %s', $identifier, $key);
                }
            }

            if (array_key_exists('slug', $manifest)) {
                if (! is_string($slug) || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
                    $problems[] = sprintf(
                        'BAD_SLUG %s slug must match ^[a-z0-9][a-z0-9_-]*$',
                        $identifier,
                    );
                }

                if (is_string($slug) && $slug !== '') {
                    if (isset($seenSlugs[$slug])) {
                        $problems[] = sprintf(
                            'DUPLICATE_SLUG %s %s duplicates %s',
                            $identifier,
                            $this->relativePath($manifestPath),
                            $seenSlugs[$slug],
                        );
                    } else {
                        $seenSlugs[$slug] = $this->relativePath($manifestPath);
                    }
                }
            }

            if (array_key_exists('base_url', $manifest) && ! self::isValidBaseUrl($manifest['base_url'])) {
                $problems[] = sprintf(
                    'BAD_BASE_URL %s base_url must be an absolute HTTP(S) URL without credentials, query, or fragment',
                    $identifier,
                );
            }

            $adapter = $manifest['adapter'] ?? null;
            $class = is_array($adapter) ? ($adapter['class'] ?? null) : null;
            if (! is_string($class) || ! class_exists($class)) {
                $problems[] = sprintf('MISSING_CLASS %s adapter.class does not name an existing class', $identifier);
            }

            $capabilities = is_array($adapter) ? ($adapter['capabilities'] ?? null) : null;
            if (! self::hasCapabilities($capabilities)) {
                $problems[] = sprintf(
                    'NO_CAPABILITIES %s adapter.capabilities must be a non-empty list of strings',
                    $identifier,
                );
            }
        }

        sort($problems, SORT_STRING);

        return $problems;
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $manifestPath): array
    {
        if (! is_readable($manifestPath)) {
            throw new RuntimeException(sprintf('Manifest is unreadable: %s', $this->relativePath($manifestPath)));
        }

        $contents = file_get_contents($manifestPath);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Manifest is unreadable: %s', $this->relativePath($manifestPath)));
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Manifest contains invalid JSON: %s', $this->relativePath($manifestPath)),
                previous: $exception,
            );
        }

        return is_array($manifest) ? $manifest : [];
    }

    private function identifier(mixed $slug, string $manifestPath): string
    {
        if (is_string($slug) && preg_match(self::SLUG_PATTERN, $slug) === 1) {
            return $slug;
        }

        return $this->relativePath($manifestPath);
    }

    private function relativePath(string $path): string
    {
        return substr($path, strlen(rtrim($this->adaptersDirectory, DIRECTORY_SEPARATOR)) + 1);
    }

    private static function isValidBaseUrl(mixed $baseUrl): bool
    {
        if (! is_string($baseUrl) || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($baseUrl);
        if (! is_array($parts)) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        if (! is_string($parts['host'] ?? null) || $parts['host'] === '') {
            return false;
        }

        return ! array_key_exists('user', $parts)
            && ! array_key_exists('pass', $parts)
            && ! array_key_exists('query', $parts)
            && ! array_key_exists('fragment', $parts);
    }

    private static function hasCapabilities(mixed $capabilities): bool
    {
        if (! is_array($capabilities) || ! array_is_list($capabilities) || $capabilities === []) {
            return false;
        }

        foreach ($capabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                return false;
            }
        }

        return true;
    }
}
