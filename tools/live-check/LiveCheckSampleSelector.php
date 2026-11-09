<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use InvalidArgumentException;
use JOOservices\CrawlerX\Enums\CrawlType;

final class LiveCheckSampleSelector
{
    /**
     * @param array<string, mixed> $manifest
     * @return list<array{slug: string, adapter_name: string, role: string, name: string, type: CrawlType, url: string}>
     */
    public function select(array $manifest): array
    {
        $slug = $manifest['slug'] ?? null;
        $adapterName = $manifest['name'] ?? null;
        if (! is_string($slug) || $slug === '' || ! is_string($adapterName) || $adapterName === '') {
            throw new InvalidArgumentException('Manifest must define a slug and name.');
        }

        $runtime = is_array($manifest['runtime'] ?? null) ? $manifest['runtime'] : [];
        $samples = is_array($runtime['fixtureSamples'] ?? null) ? $runtime['fixtureSamples'] : [];
        $live = is_array($runtime['live'] ?? null) ? $runtime['live'] : [];
        $adapter = is_array($manifest['adapter'] ?? null) ? $manifest['adapter'] : [];
        $capabilities = is_array($adapter['capabilities'] ?? null)
            ? array_values(array_filter($adapter['capabilities'], is_string(...)))
            : [];
        $selected = [];

        foreach (['listing', 'detail'] as $role) {
            $sample = $this->liveSample($live[$role] ?? null, $role, $capabilities)
                ?? $this->fixtureSample($samples, $role, $capabilities);

            if ($sample === null) {
                continue;
            }

            $selected[] = [
                'slug' => $slug,
                'adapter_name' => $adapterName,
                'role' => $role,
                'name' => $sample['name'],
                'type' => $sample['type'],
                'url' => $sample['url'],
            ];
        }

        if ($selected === []) {
            throw new InvalidArgumentException("Manifest [{$slug}] has no supported live-check samples.");
        }

        return $selected;
    }

    /**
     * @param mixed $sample
     * @return array{name: string, type: CrawlType, url: string}|null
     */
    private function liveSample(mixed $sample, string $role, array $capabilities): ?array
    {
        if ($sample === null) {
            return null;
        }

        if (! is_array($sample)
            || ! is_string($sample['url'] ?? null)
            || trim($sample['url']) === ''
            || ! is_string($sample['type'] ?? null)
        ) {
            throw new InvalidArgumentException("Live sample [{$role}] must define a URL and crawl type.");
        }

        $type = CrawlType::tryFrom($sample['type']);
        if ($type === null) {
            throw new InvalidArgumentException("Live sample [{$role}] has an unsupported crawl type.");
        }

        if (! in_array($type, $this->roleTypes($role), true) || ! in_array($type->value, $capabilities, true)) {
            throw new InvalidArgumentException("Live sample [{$role}] must match an adapter capability.");
        }

        $name = is_string($sample['name'] ?? null) && trim($sample['name']) !== ''
            ? trim($sample['name'])
            : "live-{$role}";

        return ['name' => $name, 'type' => $type, 'url' => trim($sample['url'])];
    }

    /**
     * @param list<mixed> $samples
     * @return array{name: string, type: CrawlType, url: string}|null
     */
    private function fixtureSample(array $samples, string $role, array $capabilities): ?array
    {
        foreach ($samples as $sample) {
            if (! is_array($sample)
                || ! is_string($sample['url'] ?? null)
                || trim($sample['url']) === ''
                || ! is_string($sample['type'] ?? null)
                || ! is_string($sample['name'] ?? null)
                || trim($sample['name']) === ''
            ) {
                continue;
            }

            $type = CrawlType::tryFrom($sample['type']);
            if ($type !== null
                && in_array($type, $this->roleTypes($role), true)
                && in_array($type->value, $capabilities, true)
            ) {
                return ['name' => trim($sample['name']), 'type' => $type, 'url' => trim($sample['url'])];
            }
        }

        return null;
    }

    /** @return list<CrawlType> */
    private function roleTypes(string $role): array
    {
        return $role === 'listing'
            ? [CrawlType::Listing, CrawlType::PerformerListing]
            : [CrawlType::Detail, CrawlType::PerformerDetail, CrawlType::Gallery];
    }
}
