<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class AdapterManifestDto extends Dto
{
    /**
     * @param  list<string>  $capabilities
     * @param  array<string, array<string, mixed>>  $targetProfiles
     * @param  list<array<string, mixed>>  $defaultTargets
     * @param  array<string, mixed>  $defaultCrawlConfig
     * @param  array<string, float>|null  $defaultThrottle
     * @param  array<string, string>|null  $browserHeaders
     * @param  array<string, mixed>|null  $queueSupervisor
     * @param  list<class-string>  $debugTypes
     * @param  list<FixtureSampleDto>  $fixtureSamples
     * @param  list<array<string, mixed>>  $configSchema
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $baseUrl,
        public readonly string $adapterClass,
        public readonly string $pagination,
        public readonly array $capabilities,
        public readonly array $targetProfiles,
        public readonly array $defaultTargets,
        public readonly array $defaultCrawlConfig,
        public readonly ?array $defaultThrottle = null,
        public readonly ?array $browserHeaders = null,
        public readonly bool $playwrightFetchEnabled = false,
        public readonly ?array $queueSupervisor = null,
        public readonly array $fixtureSamples = [],
        public readonly array $configSchema = [],
        public readonly array $debugTypes = [],
    ) {
    }
}
