<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Digitalark;

use JOOservices\CrawlerX\Adapters\Digitalark\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class DigitalarkDetailTest extends TestCase
{
    private const URL = 'https://shop.digital-ark.com/view/item/000000002230';

    private const COVER = 'https://makeshop-multi-images.akamaized.net/degea/itemimages/000000002230_GLUEsRE.jpg';

    public function test_identity_fields_come_from_the_item_url_and_heading(): void
    {
        $item = $this->detail();

        self::assertSame(self::URL, $item->url);
        self::assertSame('movie', $item->entityType);
        self::assertSame('000000002230', $this->movie()['external_id']);
        self::assertSame('000000002230', $this->movie()['code']);
        self::assertSame('誘惑ご奉仕ドスケベメイドは発情マゾ痴女ビッチ　ご主人様専属誘惑お漏らしデカ尻変態黒ギャル', $this->movie()['title']);
    }

    public function test_release_date_is_converted_to_iso_and_keeps_the_raw_text(): void
    {
        self::assertSame('2025-12-09', $this->movie()['date']);
        self::assertSame('2025年12月9日', $this->movie()['metadata']['release_date_raw']);
    }

    public function test_runtime_is_converted_to_whole_minutes(): void
    {
        self::assertSame(135, $this->movie()['duration']);
    }

    public function test_description_is_the_product_synopsis(): void
    {
        self::assertStringStartsWith('スケベ乳首ビンビンにして淫乱オマ○コ電マに押しつけアヘ顔', (string) $this->movie()['description']);
    }

    public function test_director_label_and_product_code_map_to_standard_or_named_metadata(): void
    {
        $metadata = $this->movie()['metadata'];

        self::assertSame('カシマ', $metadata['director']);
        self::assertSame('DIGITALARK', $metadata['label']);
        self::assertSame('flav410', $metadata['product_code']);
        self::assertSame('000000002230', $metadata['product_id']);
        self::assertSame('DIGITAL ARK', $metadata['maker']);
    }

    public function test_top_price_is_the_item_price_in_yen(): void
    {
        self::assertSame(['amount' => 3190.0, 'currency' => 'JPY', 'label' => '￥3,190（税込価格）'], $this->movie()['metadata']['price']);
    }

    public function test_every_labelled_row_is_kept_as_a_source_field(): void
    {
        self::assertSame([
            '商品コード' => 'flav410',
            '発売日' => '2025年12月9日',
            '収録時間' => '135分',
            '監督' => 'カシマ',
            'レーベル' => 'DIGITALARK',
            'HD動画（ダウンロード、ストリーミング）価格' => '2880円（税込）',
            'SD動画（ダウンロード、ストリーミング）価格' => '2380円（税込）',
        ], $this->movie()['metadata']['source_fields']);
    }

    public function test_keyword_links_are_the_tags_in_page_order(): void
    {
        self::assertSame(
            ['瀬那ルミナ', '痴女', '放尿', 'お漏らし', 'パンスト', 'タイツ', '尻', 'メイド', '黒ギャル', 'ギャル'],
            $this->movie()['tags'],
        );
    }

    public function test_no_performer_is_claimed_because_the_page_labels_none(): void
    {
        self::assertSame([], $this->movie()['performers']);
    }

    public function test_cover_is_the_full_size_item_image(): void
    {
        self::assertSame(self::COVER, $this->movie()['cover_url']);
    }

    public function test_gallery_holds_only_the_work_images_present_in_the_raw_html(): void
    {
        // The raw page carries one work image (the cover). The DVD gallery is loaded by script from another host
        // and is not part of the shop page, so it is not extracted.
        self::assertCount(1, $this->movie()['screenshots']);
        self::assertSame(self::COVER, $this->movie()['screenshots'][0]['url']);
        self::assertNull($this->movie()['screenshots'][0]['thumbnail_url']);
    }

    public function test_page_without_an_item_heading_is_rejected(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Detail($this->clientWithHtml('<html><body><main>nothing</main></body></html>')))->execute(new CrawlRequestDto(url: self::URL));
    }

    private function detail(): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('digitalark/detail-000000002230.html')))->execute(new CrawlRequestDto(url: self::URL));
    }

    /** @return array<string, mixed> */
    private function movie(): array
    {
        return $this->detail()->meta['movie'];
    }
}
