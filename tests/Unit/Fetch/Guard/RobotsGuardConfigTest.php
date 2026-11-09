<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Adapters\Onejav\OnejavCrawler;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use PHPUnit\Framework\TestCase;

final class RobotsGuardConfigTest extends TestCase
{
    public function test_respect_robots_defaults_to_true_when_unset(): void
    {
        $original = getenv('CRAWLERX_RESPECT_ROBOTS');
        putenv('CRAWLERX_RESPECT_ROBOTS');

        try {
            self::assertTrue(FetchRuntimeConfig::fromEnvironment()->respectRobots);
        } finally {
            putenv($original === false ? 'CRAWLERX_RESPECT_ROBOTS' : 'CRAWLERX_RESPECT_ROBOTS=' . $original);
        }
    }

    public function test_respect_robots_can_be_disabled_globally_via_environment(): void
    {
        $original = getenv('CRAWLERX_RESPECT_ROBOTS');
        putenv('CRAWLERX_RESPECT_ROBOTS=false');

        try {
            self::assertFalse(FetchRuntimeConfig::fromEnvironment()->respectRobots);
        } finally {
            putenv($original === false ? 'CRAWLERX_RESPECT_ROBOTS' : 'CRAWLERX_RESPECT_ROBOTS=' . $original);
        }
    }

    public function test_respect_robots_can_be_disabled_per_site_from_the_manifest(): void
    {
        $manifest = new AdapterManifestDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            adapterClass: OnejavCrawler::class,
            pagination: 'query',
            capabilities: [],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: ['respect_robots' => false],
        );

        self::assertFalse(SiteProfileDto::fromManifest($manifest)->respectRobots);
    }

    public function test_respect_robots_is_null_when_the_manifest_does_not_declare_it(): void
    {
        $manifest = new AdapterManifestDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            adapterClass: OnejavCrawler::class,
            pagination: 'query',
            capabilities: [],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: [],
        );

        self::assertNull(SiteProfileDto::fromManifest($manifest)->respectRobots);
    }
}
