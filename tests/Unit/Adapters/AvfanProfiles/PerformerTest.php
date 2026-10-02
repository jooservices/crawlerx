<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvfanProfiles;

use JOOservices\CrawlerX\Adapters\AvfanProfiles\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\AvfanProfiles\Types\PerformerListing;
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
        $client = $this->clientWithFixture('AvfanProfiles/listing-cup-a.html');
        $result = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://av-fan.tokyo/actress/cup/A.html',
            type: CrawlType::PerformerListing,
        ));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame('performer', $result->entityType);
        self::assertNotEmpty($result->items);
        self::assertFalse($result->pagination->hasNextPage);

        $first = $result->items[0];
        self::assertStringStartsWith('https://av-fan.tokyo/actress/', $first->url);
        self::assertStringEndsWith('.html', $first->url);
        self::assertNotSame('', trim((string) $first->meta['performer']['name'] ?? ''));
        self::assertNotNull($first->meta['performer']['external_id']);
    }

    public function test_listing_throws_when_no_performer_cards(): void
    {
        $client = $this->clientWithHtml('<html><body><div></div></body></html>');

        $this->expectException(CrawlParseException::class);

        (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://av-fan.tokyo/actress/cup/empty.html',
            type: CrawlType::PerformerListing,
        ));
    }

    public function test_detail_parses_bio_fields_from_live_fixture(): void
    {
        $client = $this->clientWithFixture('AvfanProfiles/detail-arisu-mai.html');
        $result = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://av-fan.tokyo/actress/%E6%9C%89%E6%A0%96%E8%88%9E%E8%A1%A3.html',
            type: CrawlType::PerformerDetail,
        ));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('performer', $result->entityType);

        $performer = $result->meta['performer'] ?? null;
        self::assertIsArray($performer);
        self::assertSame('有栖舞衣', $performer['name']);
        self::assertSame('ありすまい', $performer['metadata']['name_kana']);
        self::assertSame('2003/03/06同じ誕生日', $performer['birth_date_raw']);
        self::assertSame('T161 B83(D) W58 H94', $performer['size_raw']);
        self::assertSame('D', $performer['metadata']['cup_size']);
        self::assertSame('東京都', $performer['metadata']['birthplace']);
        self::assertSame('O', $performer['metadata']['blood_type']);
        self::assertSame('旅行', $performer['metadata']['hobby']);
        self::assertSame('20230322', $performer['metadata']['debut_date_raw']);
        self::assertSame('バンビプロモーション', $performer['metadata']['agency']);
        self::assertStringContainsString('bambi.ne.jp', (string) $performer['metadata']['official_url']);
        self::assertStringStartsWith('http://pics.dmm.co.jp/mono/actjpgs/', (string) $performer['profile_image_url']);
    }

    public function test_detail_throws_when_name_missing(): void
    {
        $client = $this->clientWithHtml('<html><body><div class="actress-img"></div></body></html>');

        $this->expectException(CrawlParseException::class);

        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://av-fan.tokyo/actress/some-performer.html',
            type: CrawlType::PerformerDetail,
        ));
    }

    public function test_detail_throws_without_usable_bio(): void
    {
        $html = '<html><body><div class="a-name">Test Performer</div></body></html>';
        $client = $this->clientWithHtml($html);

        $this->expectException(CrawlParseException::class);

        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://av-fan.tokyo/actress/test-performer.html',
            type: CrawlType::PerformerDetail,
        ));
    }
}
