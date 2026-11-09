<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Avactress;

use JOOservices\CrawlerX\Adapters\Avactress\Selectors;
use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_each_profile_keeps_every_work_cover_and_thumbnail_pair(): void
    {
        foreach ([
            ['performer-26225.html', 'https://av-actress.com/26225/x'],
            ['performer-1044864.html', 'https://av-actress.com/1044864/x'],
        ] as [$fixtureName, $url]) {
            $fixture = 'avactress/' . $fixtureName;
            $expected = (new Crawler($this->loadFixture($fixture)))->filter(Selectors::WORK)->reduce(
                static fn(Crawler $work): bool => $work->filter(Selectors::IMAGE)->count() > 0,
            )->count();
            $performer = $this->item(PerformerDetail::class, $fixture, $url, CrawlType::PerformerDetail)->meta['performer'];
            $images = $performer['metadata']['images'];

            self::assertGreaterThan(0, $expected, $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($images), $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column($images, 'url')), $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column($images, 'thumb_url')), $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column($performer['metadata']['works'], 'cover_url')), $fixtureName);
        }
    }

    public function test_performer_listings_keep_each_real_profile_photo(): void
    {
        foreach ([
            ['performers-new.html', 'https://av-actress.com/new'],
            ['performers-popular.html', 'https://av-actress.com/popular'],
        ] as [$fixtureName, $url]) {
            $fixture = 'avactress/' . $fixtureName;
            $expected = (new Crawler($this->loadFixture($fixture)))->filter(Selectors::CARD)->reduce(
                static function (Crawler $card): bool {
                    if ($card->filter(Selectors::CARD_NAME)->count() === 0 || $card->filter(Selectors::IMAGE)->count() === 0) {
                        return false;
                    }

                    $image = $card->filter(Selectors::IMAGE)->first();
                    $source = $image->attr('src') ?? $image->attr('data-src');

                    return is_string($source) && $source !== '' && ! str_contains($source, 'placeholder');
                },
            )->count();
            $result = $this->list(PerformerListing::class, $fixture, $url, CrawlType::PerformerListing);
            $performers = $this->metaOf($result->items, 'performer');

            self::assertGreaterThan(0, $expected, $fixtureName);
            $images = array_merge(...array_map(
                static fn(array $performer): array => is_array($performer['metadata']['images'] ?? null) ? $performer['metadata']['images'] : [],
                $performers,
            ));

            self::assertGreaterThanOrEqual($expected, count($images), $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column($images, 'url')), $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column($images, 'thumb_url')), $fixtureName);
        }
    }
}
