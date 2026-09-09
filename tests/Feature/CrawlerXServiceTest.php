<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Services\CrawlerXService;
use JOOservices\CrawlerX\Tests\Support\FakeServiceAdapter;
use JOOservices\CrawlerX\Tests\TestCase;

final class CrawlerXServiceTest extends TestCase
{
    public function test_service_resolves_selected_site_and_returns_adapter_dto(): void
    {
        $registry = new AdapterRegistry(new ClientFactory());
        $registry->register('fake', FakeServiceAdapter::class);

        $result = (new CrawlerXService($registry, new AdapterExecutor()))
            ->site('fake')
            ->crawl(new CrawlRequestDto(url: 'https://example.test/detail', type: CrawlType::Detail));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('https://example.test/detail', $result->url);
        self::assertSame('fake', $result->meta['movie']['external_id']);
        self::assertSame('feature-test', $result->meta['movie']['metadata']['source']);
    }

    public function test_site_selection_does_not_mutate_original_service_instance(): void
    {
        $registry = new AdapterRegistry(new ClientFactory());
        $registry->register('fake', FakeServiceAdapter::class);

        $service = new CrawlerXService($registry, new AdapterExecutor());
        $selected = $service->site('fake');

        self::assertNotSame($service, $selected);

        $this->expectException(AdapterNotFoundException::class);
        $service->crawl(new CrawlRequestDto(url: 'https://example.test/detail', type: CrawlType::Detail));
    }

    public function test_factory_registers_all_manifest_adapters(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        self::assertCount(32, $manifests->slugs());
    }
}
