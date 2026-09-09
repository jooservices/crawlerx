<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Adapters\Avfan\AvfanCrawler;
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
        self::assertSame('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36', $options['headers']['User-Agent']);
    }
}
