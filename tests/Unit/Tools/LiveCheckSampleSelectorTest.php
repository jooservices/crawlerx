<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckSampleSelector;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckSampleSelector.php';

final class LiveCheckSampleSelectorTest extends TestCase
{
    public function test_it_selects_one_sample_per_supported_role_from_fixture_samples(): void
    {
        $samples = (new LiveCheckSampleSelector())->select([
            'slug' => 'sample-site',
            'name' => 'Sample Site',
            'adapter' => ['capabilities' => ['listing', 'detail']],
            'runtime' => [
                'fixtureSamples' => [
                    ['url' => 'https://example.test/other', 'name' => 'other.html', 'type' => 'supporting'],
                    ['url' => 'https://example.test/list', 'name' => 'list.html', 'type' => 'listing'],
                    ['url' => 'https://example.test/detail', 'name' => 'detail.html', 'type' => 'detail'],
                    ['url' => 'https://example.test/detail/2', 'name' => 'detail-2.html', 'type' => 'detail'],
                ],
            ],
        ]);

        self::assertCount(2, $samples);
        self::assertSame(['listing', 'detail'], array_column($samples, 'role'));
        self::assertSame(CrawlType::Listing, $samples[0]['type']);
        self::assertTrue($samples[0]['fallback_required']);
        self::assertSame('https://example.test/detail', $samples[1]['url']);
    }

    public function test_it_marks_http_only_profiles_as_not_requiring_a_fallback(): void
    {
        $samples = (new LiveCheckSampleSelector())->select([
            'slug' => 'http-site',
            'name' => 'HTTP Site',
            'adapter' => ['capabilities' => ['listing']],
            'runtime' => [
                'fetch' => ['profile' => 'http_only'],
                'fixtureSamples' => [
                    ['url' => 'https://example.test/list', 'name' => 'list.html', 'type' => 'listing'],
                ],
            ],
        ]);

        self::assertFalse($samples[0]['fallback_required']);
    }

    public function test_it_uses_optional_live_samples_and_gallery_when_detail_is_unavailable(): void
    {
        $samples = (new LiveCheckSampleSelector())->select([
            'slug' => 'gallery-site',
            'name' => 'Gallery Site',
            'adapter' => ['capabilities' => ['gallery']],
            'runtime' => [
                'fixtureSamples' => [
                    ['url' => 'https://example.test/gallery', 'name' => 'gallery.html', 'type' => 'gallery'],
                ],
                'live' => [
                    'detail' => [
                        'url' => 'https://example.test/live-gallery',
                        'type' => 'gallery',
                        'name' => 'live-gallery',
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $samples);
        self::assertSame('detail', $samples[0]['role']);
        self::assertSame(CrawlType::Gallery, $samples[0]['type']);
        self::assertSame('https://example.test/live-gallery', $samples[0]['url']);
    }
}
