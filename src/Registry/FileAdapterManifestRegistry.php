<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Registry;

use InvalidArgumentException;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Contracts\AdapterManifestRegistry;
use JOOservices\CrawlerX\Contracts\SiteAdapter;

final class FileAdapterManifestRegistry implements AdapterManifestRegistry
{
    /** @var array<string, AdapterManifestDto>|null */
    private ?array $manifests = null;

    /** @return array<string, AdapterManifestDto> */
    public function all(): array
    {
        return $this->load();
    }

    public function get(string $slug): ?AdapterManifestDto
    {
        return $this->load()[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return isset($this->load()[$slug]);
    }

    /**
     * @return array<string, class-string<SiteAdapter>>
     */
    public function adapterMap(): array
    {
        $map = [];

        foreach ($this->load() as $slug => $manifest) {
            if (! is_a($manifest->adapterClass, SiteAdapter::class, true)) {
                continue;
            }

            $map[$slug] = $manifest->adapterClass;
        }

        return $map;
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return array_keys($this->load());
    }

    /** @return array<string, AdapterManifestDto> */
    private function load(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        $basePath = dirname(__DIR__) . '/Adapters';
        $globbed = glob($basePath . '/*/manifest.json');
        $paths = $globbed === false ? [] : $globbed;
        $manifests = [];

        foreach ($paths as $path) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new InvalidArgumentException("Unable to read manifest [{$path}].");
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException("Manifest [{$path}] must be valid JSON.");
            }

            $manifest = $this->toDto($decoded, $path);
            $this->validateManifest($manifest, $path);

            if (isset($manifests[$manifest->slug])) {
                throw new InvalidArgumentException("Duplicate adapter manifest slug [{$manifest->slug}].");
            }

            $manifests[$manifest->slug] = $manifest;
        }

        $this->manifests = $manifests;

        return $this->manifests;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toDto(array $data, string $path): AdapterManifestDto
    {
        $slug = is_string($data['slug'] ?? null) ? $data['slug'] : '';
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $baseUrl = is_string($data['base_url'] ?? null) ? $data['base_url'] : '';

        $adapter = is_array($data['adapter'] ?? null) ? $data['adapter'] : [];
        $adapterClass = is_string($adapter['class'] ?? null) ? $adapter['class'] : '';

        $paginationRaw = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
        $pagination = is_string($paginationRaw['strategy'] ?? null) ? $paginationRaw['strategy'] : 'query';

        $runtime = is_array($data['runtime'] ?? null) ? $data['runtime'] : [];
        /** @var array<string, mixed> $runtime */
        $targetProfiles = is_array($runtime['targetProfiles'] ?? null) ? $runtime['targetProfiles'] : [];
        $defaultTargets = is_array($runtime['defaultTargets'] ?? null) ? $runtime['defaultTargets'] : [];
        $defaultCrawlConfig = is_array($runtime['defaultCrawlConfig'] ?? null) ? $runtime['defaultCrawlConfig'] : [];
        $defaultThrottle = is_array($runtime['defaultThrottle'] ?? null) ? $runtime['defaultThrottle'] : null;
        $browserHeaders = is_array($runtime['browserHeaders'] ?? null) ? $runtime['browserHeaders'] : null;
        $playwrightFetchEnabled = (bool) ($runtime['playwrightFetchEnabled'] ?? false);
        $queueSupervisor = is_array($runtime['queueSupervisor'] ?? null) ? $runtime['queueSupervisor'] : null;
        $fixtureSamples = $this->fixtureSamplesFromRuntime($runtime);
        $configSchema = is_array($data['config_schema'] ?? null) ? $data['config_schema'] : [];

        if ($slug === '' || $name === '' || $baseUrl === '' || $adapterClass === '') {
            throw new InvalidArgumentException("Manifest [{$path}] is missing required slug, name, base_url, or adapter.class.");
        }

        /** @var array<string, array<string, mixed>> $targetProfiles */
        /** @var list<array<string, mixed>> $defaultTargets */
        /** @var array<string, mixed> $defaultCrawlConfig */
        /** @var array<string, float>|null $defaultThrottle */
        /** @var array<string, string>|null $browserHeaders */
        /** @var array<string, mixed>|null $queueSupervisor */

        return new AdapterManifestDto(
            slug: $slug,
            displayName: $name,
            baseUrl: $baseUrl,
            adapterClass: $adapterClass,
            pagination: $pagination,
            capabilities: is_array($adapter['capabilities'] ?? null) ? array_values(array_filter($adapter['capabilities'], is_string(...))) : [],
            targetProfiles: $targetProfiles,
            defaultTargets: $defaultTargets,
            defaultCrawlConfig: $defaultCrawlConfig,
            defaultThrottle: $defaultThrottle,
            browserHeaders: $browserHeaders,
            playwrightFetchEnabled: $playwrightFetchEnabled,
            queueSupervisor: $queueSupervisor,
            fixtureSamples: $fixtureSamples,
            configSchema: $this->normalizeConfigSchema($configSchema),
        );
    }

    /**
     * @param  array<mixed>  $schema
     * @return list<array<string, mixed>>
     */
    private function normalizeConfigSchema(array $schema): array
    {
        $normalized = [];
        foreach ($schema as $field) {
            if (! is_array($field)) {
                continue;
            }

            $normalizedField = [];
            foreach ($field as $key => $value) {
                if (is_string($key)) {
                    $normalizedField[$key] = $value;
                }
            }

            if ($normalizedField !== []) {
                $normalized[] = $normalizedField;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $runtime
     * @return list<FixtureSampleDto>
     */
    private function fixtureSamplesFromRuntime(array $runtime): array
    {
        $raw = $runtime['fixtureSamples'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $samples = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /** @var array<string, mixed> $entry */
            $sample = FixtureSampleDto::fromManifestEntry($entry);
            if ($sample instanceof FixtureSampleDto) {
                $samples[] = $sample;
            }
        }

        return $samples;
    }

    private function validateManifest(AdapterManifestDto $manifest, string $path): void
    {
        if ($manifest->slug === '' || $manifest->displayName === '' || $manifest->baseUrl === '') {
            throw new InvalidArgumentException("Manifest [{$path}] is missing required slug, displayName, or baseUrl.");
        }

        if (! is_subclass_of($manifest->adapterClass, SiteAdapter::class)) {
            throw new InvalidArgumentException("Manifest [{$manifest->slug}] adapterClass must implement SiteAdapter.");
        }
    }
}
