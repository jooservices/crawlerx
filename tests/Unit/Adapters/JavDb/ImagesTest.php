<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavDb;

use JOOservices\CrawlerX\Adapters\JavDb\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavDb\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_the_movie_page_keeps_the_cover_and_every_sample_pair_but_not_the_recommendations(): void
    {
        $movie = $this->item(Detail::class, 'javdb/detail.html', 'https://javdb.com/v/a8yV0p', CrawlType::Detail)->meta['movie'];

        self::assertSame('https://c0.jdbstatic.com/covers/a8/a8yV0p.jpg', $movie['cover_url']);
        self::assertCount(11, $this->column($movie['screenshots'], 'url'));
        self::assertCount(11, $this->column($movie['screenshots'], 'thumbnail_url'));
        self::assertSame([], array_filter($this->column($movie['screenshots'], 'url'), static fn(string $url): bool => str_contains($url, '/thumbs/') || str_contains($url, '/ads/')));
    }

    public function test_every_listing_card_keeps_its_cover(): void
    {
        $result = $this->list(Listing::class, 'javdb/listing.html', 'https://javdb.com/', CrawlType::Listing);

        self::assertCount(40, $this->column($this->metaOf($result->items, 'movie'), 'cover_url'));
    }
}
