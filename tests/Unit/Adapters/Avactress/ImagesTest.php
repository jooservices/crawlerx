<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Avactress;

use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_the_performer_page_keeps_every_work_cover_in_the_large_size_next_to_the_thumbnail(): void
    {
        $performer = $this->item(PerformerDetail::class, 'avactress/performer-26225.html', 'https://av-actress.com/26225/x', CrawlType::PerformerDetail)->meta['performer'];
        $images = $performer['metadata']['images'];

        self::assertCount(100, $this->column($images, 'url'));
        self::assertCount(100, $this->column($images, 'thumb_url'));
        self::assertSame([], array_filter($this->column($images, 'url'), static fn(string $url): bool => ! str_ends_with($url, 'pl.jpg')));
        self::assertSame([], array_filter($this->column($images, 'thumb_url'), static fn(string $url): bool => ! str_ends_with($url, 'ps.jpg')));
        self::assertCount(100, $this->column($performer['metadata']['works'], 'cover_url'));
    }

    public function test_the_second_performer_page_keeps_its_ninety_eight_covers(): void
    {
        $performer = $this->item(PerformerDetail::class, 'avactress/performer-1044864.html', 'https://av-actress.com/1044864/x', CrawlType::PerformerDetail)->meta['performer'];

        self::assertCount(98, $this->column($performer['metadata']['images'], 'url'));
    }

    public function test_every_performer_card_with_a_cover_keeps_its_cover_pair(): void
    {
        $result = $this->list(PerformerListing::class, 'avactress/performers-new.html', 'https://av-actress.com/new', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertCount(100, $performers);
        self::assertCount(94, array_filter($performers, static fn(array $performer): bool => count($performer['metadata']['images'] ?? []) === 1));
        self::assertSame('https://pics.dmm.co.jp/digital/video/deab00010/deab00010pl.jpg', $performers[0]['metadata']['images'][0]['url']);
    }
}
