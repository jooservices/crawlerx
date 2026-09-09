<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\JavDatabase\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavDatabase\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class JavDatabaseAdapterTest extends TestCase
{
    public function test_performer_listing_parses_performer_links_and_last_page(): void
    {
        $client = $this->clientWithFixture('javdatabase/listing_page1.html');
        $list = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://www.javdatabase.com/idols/',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertSame('https://www.javdatabase.com/idols/', $list->url);
        self::assertSame(1, $list->page);
        self::assertNotEmpty($list->items);
        self::assertStringContainsString('/idols/', $list->items[0]->url);
        self::assertNotSame('', (string) $list->items[0]->meta['performer']['external_id']);
        self::assertNotSame('', (string) $list->items[0]->meta['performer']['name']);

        self::assertTrue($list->pagination->hasNextPage);
        self::assertSame('https://www.javdatabase.com/idols/page/2/', $list->pagination->nextUrl);
        self::assertSame(2, $list->pagination->nextPage);
        self::assertGreaterThan(1, (int) $list->pagination->lastPage);
    }

    public function test_performer_listing_ignores_non_performer_links(): void
    {
        $client = $this->clientWithFixture('javdatabase/listing_page1.html');
        $list = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://www.javdatabase.com/idols/',
            type: CrawlType::Listing,
            page: 1,
        ));

        foreach ($list->items as $item) {
            self::assertMatchesRegularExpression('#^https://www\.javdatabase\.com/idols/[^/?]+/?$#', $item->url);
        }
    }

    public function test_performer_detail_parses_full_bio_fields(): void
    {
        $client = $this->clientWithFixture('javdatabase/detail_yua_mikami.html');
        $item = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.javdatabase.com/idols/yua-mikami/',
            type: CrawlType::Detail,
        ));

        self::assertSame('yua-mikami', $item->meta['performer']['external_id']);
        self::assertNotSame('', (string) $item->meta['performer']['name']);

        $meta = $item->meta;
        self::assertIsArray($meta);
    }

    public function test_performer_detail_handles_missing_bio_fields_as_null(): void
    {
        $client = $this->clientWithFixture('javdatabase/detail_partial_fields.html');
        $item = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.javdatabase.com/idols/maya-mutsuki/',
            type: CrawlType::Detail,
        ));

        self::assertSame('maya-mutsuki', $item->meta['performer']['external_id']);
        self::assertSame('Maya Mutsuki', $item->meta['performer']['name']);

        $meta = $item->meta['performer'];
        self::assertNull($meta['metadata']['age'] ?? null);
        self::assertNull($meta['birth_date_raw'] ?? null);
        self::assertSame('2026-07-10', $meta['metadata']['debut_date_raw'] ?? null);
        self::assertNull($meta['metadata']['birthplace'] ?? null);
        self::assertNull($meta['metadata']['zodiac_sign'] ?? null);
        self::assertNull($meta['metadata']['blood_type'] ?? null);
        self::assertNull($meta['size_raw'] ?? null);
        self::assertNull($meta['height_raw'] ?? null);
        self::assertNull($meta['metadata']['shoe_size_raw'] ?? null);
        self::assertSame('睦月まや', $meta['name_japanese'] ?? null);
        self::assertSame('0', $meta['metadata']['favorite_count_raw'] ?? null);

        self::assertSame('https://www.javdatabase.com/idolimages/full/maya-mutsuki.webp', $meta['profile_image_url'] ?? null);
    }

    public function test_performer_detail_throws_if_name_and_external_id_empty(): void
    {
        $client = $this->clientWithHtml('<html><body><div>No data</div></body></html>');

        $this->expectException(\RuntimeException::class);
        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.javdatabase.com/idols/unknown-idol/',
            type: CrawlType::Detail,
        ));
    }
}
