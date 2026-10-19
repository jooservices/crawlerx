<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Dto;

use Faker\Factory;
use JOOservices\CrawlerX\Adapters\Onejav\OnejavCrawler;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use PHPUnit\Framework\TestCase;

final class SiteProfileDtoTest extends TestCase
{
    public function test_from_manifest_discards_non_numeric_throttle_values(): void
    {
        $faker = Factory::create();
        $manifest = new AdapterManifestDto(
            slug: $faker->unique()->slug(),
            displayName: $faker->words(2, true),
            baseUrl: 'https://' . $faker->unique()->domainName(),
            adapterClass: OnejavCrawler::class,
            pagination: $faker->word(),
            capabilities: [],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: [],
            defaultThrottle: [
                'default_gap_seconds' => 4,
                'min_gap_seconds' => '2',
                'max_gap_seconds' => 30.5,
                'unknown_gap_seconds' => INF,
            ],
        );

        $profile = SiteProfileDto::fromManifest($manifest);

        self::assertSame([
            'default_gap_seconds' => 4.0,
            'max_gap_seconds' => 30.5,
        ], $profile->defaultThrottle);
    }

    public function test_from_manifest_uses_null_when_no_numeric_throttle_values_remain(): void
    {
        $faker = Factory::create();
        $manifest = new AdapterManifestDto(
            slug: $faker->unique()->slug(),
            displayName: $faker->words(2, true),
            baseUrl: 'https://' . $faker->unique()->domainName(),
            adapterClass: OnejavCrawler::class,
            pagination: $faker->word(),
            capabilities: [],
            targetProfiles: [],
            defaultTargets: [],
            defaultCrawlConfig: [],
            defaultThrottle: ['default_gap_seconds' => $faker->word()],
        );

        self::assertNull(SiteProfileDto::fromManifest($manifest)->defaultThrottle);
    }
}
