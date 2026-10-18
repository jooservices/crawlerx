<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Xcity;

use JOOservices\CrawlerX\Adapters\Xcity\Types\Detail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\Listing;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_a_japanese_movie_page_yields_every_field(): void
    {
        $m = (new Detail($this->clientWithFixture('xcity/detail-208587.html')))
            ->execute(new CrawlRequestDto(url: 'https://xcity.jp/avod/detail/?id=208587', type: CrawlType::Detail))->meta['movie'];

        self::assertSame('HEZ888', $m['code']);
        self::assertSame('2026-05-10', $m['date']);
        self::assertSame(132, $m['duration']);
        self::assertSame(['10696'], array_column($m['performers'], 'external_id'));
        self::assertSame('https://xcity.jp/idol/detail/10696/', $m['performers'][0]['url']);
        self::assertContains('人妻', $m['tags']);
        self::assertNotEmpty($m['screenshots']);

        $meta = $m['metadata'];
        self::assertSame('ホットエンターテイメント', $meta['maker']);
        self::assertSame('ホット', $meta['label']);
        self::assertSame('2026-09-09', $meta['sales_date']);
        self::assertSame('XCT-208587', $meta['xcity_code']);
        self::assertSame(68, $meta['favorite_count']);
        self::assertStringContainsString('/maker/hot/', (string) $meta['maker_url']);
        self::assertStringContainsString('obox.jp/sample/video/', (string) $meta['sample_video_url']);
    }

    public function test_the_profile_page_yields_name_parts_birth_and_filmography(): void
    {
        $p = (new PerformerDetail($this->clientWithFixture('xcity/performer-detail-10696.html')))
            ->execute(new CrawlRequestDto(url: 'https://xcity.jp/idol/detail/10696/', type: CrawlType::PerformerDetail))->meta['performer'];

        self::assertSame('笹倉杏', $p['name']);
        self::assertSame(['緒川はる'], $p['aliases']);
        self::assertSame('1990年10月27日', $p['birth_date_raw']);
        self::assertSame('153cm', $p['height_raw']);
        self::assertSame('B96 W60 H88', $p['size_raw']);

        $meta = $p['metadata'];
        self::assertSame('ささくらあん', $meta['name_kana']);
        self::assertSame('1990-10-27', $meta['birth_date']);
        self::assertSame('東京都', $meta['birthplace']);
        self::assertSame('音楽鑑賞', $meta['hobby']);
        self::assertArrayNotHasKey('blood_type', $meta);
        self::assertCount(20, $meta['filmography']);
        self::assertSame('208587', $meta['filmography'][0]['external_id']);
        self::assertSame('https://xcity.jp/idol/detail/10696/?page=2', $meta['filmography_next_url']);
    }

    public function test_list_cards_carry_cover_actress_maker_and_flags(): void
    {
        $m = (new Listing($this->clientWithFixture('xcity/listing_page1.html')))
            ->execute(new CrawlRequestDto(url: 'https://xxx.xcity.jp/avod/list/', type: CrawlType::Listing))->items[0]->meta['movie'];

        self::assertSame('208587', $m['external_id']);
        self::assertSame('10696', $m['performers'][0]['external_id']);
        self::assertSame('Hot Entertainment', $m['metadata']['maker']);
        self::assertTrue($m['metadata']['has_free_preview']);
        self::assertNotNull($m['metadata']['cover_large_url']);
    }
}
