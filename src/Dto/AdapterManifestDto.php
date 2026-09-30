<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class AdapterManifestDto extends Dto
{
    /** Capability string → entity. */
    public const ENTITY_MOVIE = 'movie';

    public const ENTITY_PERFORMER = 'performer';

    public const ENTITY_GALLERY = 'gallery';

    /** @var array<string, string> capability => entity */
    private const CAPABILITY_ENTITY = [
        'listing' => self::ENTITY_MOVIE,
        'detail' => self::ENTITY_MOVIE,
        'search' => self::ENTITY_MOVIE,
        'performer_listing' => self::ENTITY_PERFORMER,
        'performer_detail' => self::ENTITY_PERFORMER,
        'performer_search' => self::ENTITY_PERFORMER,
        'gallery' => self::ENTITY_GALLERY,
    ];
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

    /**
     * Entity-scoped capability view derived from the flat `capabilities` list.
     *
     * @return array<string, list<string>> e.g. ['movie' => ['listing', 'detail'], 'performer' => [...]]
     */
    public function entities(): array
    {
        $entities = [];

        foreach ($this->capabilities as $capability) {
            $entity = self::CAPABILITY_ENTITY[$capability] ?? null;
            if ($entity === null) {
                continue;
            }

            $entities[$entity][] = $capability;
        }

        return $entities;
    }
}
