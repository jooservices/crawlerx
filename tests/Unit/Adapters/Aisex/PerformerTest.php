<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Aisex;

use JOOservices\CrawlerX\Adapters\Aisex\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Aisex\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class PerformerTest extends TestCase
{
    public function test_listing_parses_performer_cards(): void
    {
        $client = $this->clientWithFixture('Aisex/listing-page1.html');
        $result = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://www.aisex.jp/actress/',
            type: CrawlType::PerformerListing,
        ));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame('performer', $result->entityType);
        self::assertNotEmpty($result->items);

        $first = $result->items[0];
        self::assertStringStartsWith('https://www.aisex.jp/actress/', $first->url);
        self::assertNotSame('', trim((string) $first->meta['performer']['name'] ?? ''));
        self::assertNotNull($first->meta['performer']['external_id']);
    }

    public function test_listing_reports_pagination(): void
    {
        $client = $this->clientWithFixture('Aisex/listing-page1.html');
        $result = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://www.aisex.jp/actress/',
            type: CrawlType::PerformerListing,
        ));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertStringContainsString('?page=2', (string) $result->pagination->nextUrl);
        self::assertSame(1213, $result->pagination->lastPage);
    }

    public function test_listing_throws_when_no_performer_cards(): void
    {
        $client = $this->clientWithHtml('<html><body><div></div></body></html>');

        $this->expectException(CrawlParseException::class);

        (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://www.aisex.jp/actress/',
            type: CrawlType::PerformerListing,
        ));
    }

    public function test_detail_parses_bio_fields_from_live_fixture(): void
    {
        $client = $this->clientWithFixture('Aisex/detail-aiuti-siori.html');
        $result = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.aisex.jp/actress/1004850/',
            type: CrawlType::PerformerDetail,
        ));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('performer', $result->entityType);

        $performer = $result->meta['performer'] ?? null;
        self::assertIsArray($performer);
        self::assertSame('相内しおり', $performer['name']);
        self::assertSame('あいうちしおり', $performer['metadata']['name_kana']);
        self::assertSame('1990-11-06', $performer['birth_date_raw']);
        self::assertSame('蠍座', $performer['metadata']['zodiac_sign']);
        self::assertSame('B型', $performer['metadata']['blood_type']);
        self::assertSame('161cm', $performer['height_raw']);
        self::assertSame('B85', $performer['metadata']['bust_raw']);
        self::assertSame('D', $performer['metadata']['cup_size']);
        self::assertSame('W58', $performer['metadata']['waist_raw']);
        self::assertSame('H88', $performer['metadata']['hip_raw']);
        self::assertStringStartsWith('http://pics.dmm.co.jp/mono/actjpgs/', (string) $performer['profile_image_url']);
    }

    public function test_detail_throws_when_name_missing(): void
    {
        $client = $this->clientWithHtml('<html><body><div class="profile-image"></div></body></html>');

        $this->expectException(CrawlParseException::class);

        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.aisex.jp/actress/999999/',
            type: CrawlType::PerformerDetail,
        ));
    }

    public function test_detail_throws_without_usable_bio(): void
    {
        $html = '<html><body><h1 class="profile-name">Test Performer</h1></body></html>';
        $client = $this->clientWithHtml($html);

        $this->expectException(CrawlParseException::class);

        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.aisex.jp/actress/1/',
            type: CrawlType::PerformerDetail,
        ));
    }
}
