<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckSampleSelector;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckSampleSelector.php';

final class LiveCheckSampleSelectorPriorityTest extends TestCase
{
    public function test_fixture_order_does_not_override_listing_and_detail_preferences(): void
    {
        $samples = (new LiveCheckSampleSelector())->select([
            'slug' => 'sample-site',
            'name' => 'Sample Site',
            'adapter' => ['capabilities' => ['performer_listing', 'listing', 'gallery', 'performer_detail', 'detail']],
            'runtime' => [
                'fixtureSamples' => [
                    ['url' => 'https://example.test/performers', 'name' => 'performers.html', 'type' => 'performer_listing'],
                    ['url' => 'https://example.test/gallery', 'name' => 'gallery.html', 'type' => 'gallery'],
                    ['url' => 'https://example.test/performer/1', 'name' => 'performer.html', 'type' => 'performer_detail'],
                    ['url' => 'https://example.test/catalog', 'name' => 'listing.html', 'type' => 'listing'],
                    ['url' => 'https://example.test/item/1', 'name' => 'detail.html', 'type' => 'detail'],
                ],
            ],
        ]);

        self::assertSame(CrawlType::Listing, $samples[0]['type']);
        self::assertSame('https://example.test/catalog', $samples[0]['url']);
        self::assertSame(CrawlType::Detail, $samples[1]['type']);
        self::assertSame('https://example.test/item/1', $samples[1]['url']);
    }

    public function test_performer_detail_precedes_gallery_when_no_movie_detail_exists(): void
    {
        $samples = (new LiveCheckSampleSelector())->select([
            'slug' => 'performer-site',
            'name' => 'Performer Site',
            'adapter' => ['capabilities' => ['gallery', 'performer_detail']],
            'runtime' => [
                'fixtureSamples' => [
                    ['url' => 'https://example.test/gallery', 'name' => 'gallery.html', 'type' => 'gallery'],
                    ['url' => 'https://example.test/performer/1', 'name' => 'performer.html', 'type' => 'performer_detail'],
                ],
            ],
        ]);

        self::assertSame(CrawlType::PerformerDetail, $samples[0]['type']);
        self::assertSame('https://example.test/performer/1', $samples[0]['url']);
    }
}
