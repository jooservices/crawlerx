<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Adapters\Avfan\AvfanCrawler;
use JOOservices\CrawlerX\Adapters\AvWiki\AvWikiCrawler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class AbstractBaseCrawlerManifestDefaultsTest extends TestCase
{
    public function test_manifest_values_merge_into_crawler_default_options(): void
    {
        $manifest = new AdapterManifestDto(
            slug: 'avfan',
            displayName: 'Avfan',
            baseUrl: 'https://avfan.com',
            adapterClass: AvfanCrawler::class,
            pagination: 'query',
            capabilities: ['listing', 'detail'],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: ['timeout' => 42],
            browserHeaders: ['User-Agent' => 'TestAgent/1.0'],
        );

        $crawler = new AvfanCrawler(new ClientFactory(), $manifest);
        $options = (new \ReflectionProperty(AvfanCrawler::class, 'options'))->getValue($crawler);

        self::assertIsArray($options);
        self::assertSame('https://avfan.com', $options['base_uri']);
        self::assertSame(42, $options['timeout']);
        self::assertArrayNotHasKey('connect_timeout', $options);
        self::assertSame('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36', $options['headers']['User-Agent']);
    }

    public function test_manifest_connect_timeout_reaches_crawler_options(): void
    {
        $manifest = new AdapterManifestDto(
            slug: 'avwiki',
            displayName: 'AV-Wiki',
            baseUrl: 'https://av-wiki.net',
            adapterClass: AvWikiCrawler::class,
            pagination: 'query',
            capabilities: ['listing', 'detail'],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: ['timeout' => 45, 'connect_timeout' => 30],
        );

        $options = (new \ReflectionProperty(AvWikiCrawler::class, 'options'))
            ->getValue(new AvWikiCrawler(new ClientFactory(), $manifest));

        self::assertIsArray($options);
        self::assertSame(45, $options['timeout']);
        self::assertSame(30, $options['connect_timeout']);
    }

    public function test_avwiki_manifest_allows_for_the_hosts_per_request_delay(): void
    {
        $manifest = (new \JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry())->get('avwiki');

        self::assertNotNull($manifest);
        self::assertGreaterThanOrEqual(45, $manifest->defaultCrawlConfig['timeout']);
        self::assertGreaterThanOrEqual(45, $manifest->defaultCrawlConfig['connect_timeout']);
        self::assertGreaterThanOrEqual(10, $manifest->defaultThrottle['default_gap_seconds'] ?? 0);
        self::assertGreaterThanOrEqual(45, \JOOservices\CrawlerX\Dto\SiteProfileDto::fromManifest($manifest)->http->timeout);
    }
}
