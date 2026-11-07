<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Avjoho;

use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_every_performer_card_keeps_its_cover_in_the_large_size_next_to_the_thumbnail(): void
    {
        $result = $this->list(PerformerListing::class, 'avjoho/listing-page1.html', 'https://db.avjoho.com/category/x/', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertCount(20, $performers);
        self::assertCount(20, $this->column($performers, 'profile_image_url'));
        self::assertCount(20, $this->column(array_column($performers, 'metadata'), 'thumbnail_url'));
        self::assertSame('https://pics.dmm.co.jp/digital/video/snos00299/snos00299pl.jpg', $performers[0]['profile_image_url']);
        self::assertSame('https://pics.dmm.co.jp/digital/video/snos00299/snos00299ps.jpg', $performers[0]['metadata']['thumbnail_url']);
        self::assertSame([], array_filter($this->column($performers, 'profile_image_url'), static fn(string $url): bool => ! str_ends_with($url, 'pl.jpg')));
    }

    public function test_the_performer_page_keeps_the_large_profile_cover_and_the_notable_works(): void
    {
        $performer = $this->item(PerformerDetail::class, 'avjoho/detail-ichise-airi.html', 'https://db.avjoho.com/x/', CrawlType::PerformerDetail)->meta['performer'];

        self::assertSame('https://pics.dmm.co.jp/digital/video/pred00822/pred00822pl.jpg', $performer['profile_image_url']);
        self::assertSame(
            ['https://pics.dmm.co.jp/digital/video/pred00822/pred00822pl.jpg', 'https://pics.dmm.co.jp/digital/video/pred00834/pred00834pl.jpg'],
            $this->column($performer['metadata']['notable_works'], 'cover_url'),
        );
    }
}
