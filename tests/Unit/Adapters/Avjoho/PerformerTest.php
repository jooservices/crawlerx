<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Avjoho;

use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerListing;
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
        $client = $this->clientWithFixture('avjoho/listing-page1.html');
        $result = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://db.avjoho.com/category/%e3%83%87%e3%83%93%e3%83%a5%e3%83%bc%ef%bc%882025%e5%b9%b4-%ef%bc%89/',
            type: CrawlType::PerformerListing,
        ));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame('performer', $result->entityType);
        self::assertNotEmpty($result->items);

        $first = $result->items[0];
        self::assertStringStartsWith('https://db.avjoho.com/', $first->url);
        self::assertNotSame('', trim((string) $first->meta['performer']['name'] ?? ''));
        self::assertNotNull($first->meta['performer']['external_id']);
    }

    public function test_listing_reports_pagination(): void
    {
        $client = $this->clientWithFixture('avjoho/listing-page1.html');
        $result = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://db.avjoho.com/category/%e3%83%87%e3%83%93%e3%83%a5%e3%83%bc%ef%bc%882025%e5%b9%b4-%ef%bc%89/',
            type: CrawlType::PerformerListing,
        ));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertStringContainsString('/page/2/', (string) $result->pagination->nextUrl);
        self::assertNotNull($result->pagination->lastPage);
        self::assertGreaterThan(2, $result->pagination->lastPage);
    }

    public function test_listing_throws_when_no_performer_cards(): void
    {
        $client = $this->clientWithHtml('<html><body><div class="database"></div></body></html>');

        $this->expectException(CrawlParseException::class);

        (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://db.avjoho.com/category/empty/',
            type: CrawlType::PerformerListing,
        ));
    }

    public function test_detail_parses_bio_fields_from_live_fixture(): void
    {
        $client = $this->clientWithFixture('avjoho/detail-ichise-airi.html');
        $result = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://db.avjoho.com/%e5%b8%82%e7%80%ac%e3%81%82%e3%81%84%e3%82%8a/',
            type: CrawlType::PerformerDetail,
        ));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('performer', $result->entityType);

        $performer = $result->meta['performer'] ?? null;
        self::assertIsArray($performer);
        self::assertSame('市瀬あいり（いちせあいり）', $performer['name']);
        self::assertSame('2000年2月10日', $performer['birth_date_raw']);
        self::assertSame('160cm', $performer['height_raw']);
        self::assertSame('B88cm W56cm H90cm', $performer['size_raw']);
        self::assertSame('2025年11月18日', $performer['metadata']['debut_date_raw']);
        self::assertSame('G', $performer['metadata']['cup_size']);
        self::assertSame('東京都', $performer['metadata']['birthplace']);
        self::assertSame('A型', $performer['metadata']['blood_type']);
        self::assertSame('@ichise_airi', $performer['metadata']['sns']);
        self::assertStringContainsString('プレミアム', (string) $performer['raw_profile']['text']);
    }

    public function test_detail_parses_profile_image(): void
    {
        $client = $this->clientWithFixture('avjoho/detail-ichise-airi.html');
        $result = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://db.avjoho.com/%e5%b8%82%e7%80%ac%e3%81%82%e3%81%84%e3%82%8a/',
            type: CrawlType::PerformerDetail,
        ));

        $performer = $result->meta['performer'] ?? null;
        self::assertIsArray($performer);
        self::assertStringStartsWith('https://pics.dmm.co.jp/digital/video/', (string) $performer['profile_image_url']);
    }

    public function test_detail_throws_when_name_missing(): void
    {
        $client = $this->clientWithHtml('<html><body><div class="database"></div></body></html>');

        $this->expectException(CrawlParseException::class);

        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://db.avjoho.com/some-performer/',
            type: CrawlType::PerformerDetail,
        ));
    }

    public function test_detail_throws_without_usable_bio(): void
    {
        $html = '<html><body><h1 class="entry-title">Test Performer</h1><div class="database"></div></body></html>';
        $client = $this->clientWithHtml($html);

        $this->expectException(CrawlParseException::class);

        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://db.avjoho.com/test-performer/',
            type: CrawlType::PerformerDetail,
        ));
    }
}
