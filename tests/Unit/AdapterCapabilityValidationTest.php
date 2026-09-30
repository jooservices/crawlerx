<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use InvalidArgumentException;
use JOOservices\CrawlerX\Contracts\AdapterManifestRegistry;
use JOOservices\CrawlerX\Contracts\SearchCapable;
use JOOservices\CrawlerX\Contracts\SiteAdapter;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Tests\TestCase;

final class AdapterCapabilityValidationTest extends TestCase
{
    public function test_registry_rejects_manifest_capability_the_adapter_does_not_implement(): void
    {
        $manifests = $this->manifests(['listing']);

        $registry = new AdapterRegistry(manifests: $manifests);
        $registry->register('ghost', ManifestOnlyAdapter::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('declares capability [listing]');

        $registry->registerFromManifests($manifests);
    }

    public function test_executor_dispatches_search_capable_adapter(): void
    {
        $request = new CrawlRequestDto(
            url: 'https://example.test/search?q=abc',
            type: CrawlType::Search,
        );

        $result = (new AdapterExecutor())->execute(new SearchOnlyAdapter(), $request);

        self::assertSame('movie', $result->entityType);
        self::assertSame([], $result->items);
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function manifests(array $capabilities): AdapterManifestRegistry
    {
        return new class ($capabilities) implements AdapterManifestRegistry {
            /** @param  list<string>  $capabilities */
            public function __construct(private readonly array $capabilities)
            {
            }

            /** @return array<string, AdapterManifestDto> */
            public function all(): array
            {
                return [$this->manifest()];
            }

            public function get(string $slug): ?AdapterManifestDto
            {
                return $slug === 'ghost' ? $this->manifest() : null;
            }

            public function has(string $slug): bool
            {
                return $slug === 'ghost';
            }

            /** @return array<string, string> */
            public function adapterMap(): array
            {
                return ['ghost' => ManifestOnlyAdapter::class];
            }

            /** @return list<string> */
            public function slugs(): array
            {
                return ['ghost'];
            }

            private function manifest(): AdapterManifestDto
            {
                return new AdapterManifestDto(
                    slug: 'ghost',
                    displayName: 'Ghost',
                    baseUrl: 'https://example.test',
                    adapterClass: ManifestOnlyAdapter::class,
                    pagination: 'query',
                    capabilities: $this->capabilities,
                    targetProfiles: [],
                    defaultTargets: [],
                    defaultCrawlConfig: [],
                );
            }
        };
    }
}

final class ManifestOnlyAdapter implements SiteAdapter
{
    public function name(): string
    {
        return 'ghost';
    }
}

final class SearchOnlyAdapter implements SiteAdapter, SearchCapable
{
    public function name(): string
    {
        return 'search-only';
    }

    public function search(CrawlRequestDto $request): CrawlListResultDto
    {
        return new CrawlListResultDto(
            url: $request->url,
            page: 1,
            entityType: 'movie',
            items: [],
            pagination: new CrawlPaginationDto(1, 1, null, null, false),
        );
    }
}
