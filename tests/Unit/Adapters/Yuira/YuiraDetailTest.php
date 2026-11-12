<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Yuira;

use JOOservices\CrawlerX\Adapters\Yuira\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class YuiraDetailTest extends TestCase
{
    private const BASE = 'https://yuira.jp';

    private const URL = self::BASE . '/product/%e3%81%bc%e3%81%8f%e3%81%a8%e5%85%ab%e5%b0%ba%e6%a7%98%e3%80%80%e3%81%bd%e3%81%bd%e3%81%a3%e3%81%a8%e3%81%88%e3%81%a3%e3%81%a1%e3%81%aa%e3%83%87%e3%82%ab%e5%b0%bb%e3%81%ab%e5%ad%95%e3%81%be%e3%81%9b/';

    private const JAN_IMAGE = self::BASE . '/wp-content/uploads/2026/08/4582588351010-';

    private \JOOservices\CrawlerX\Dto\CrawlItemResultDto $item;

    /** @var array<string, mixed> */
    private array $movie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = (new Detail($this->clientWithFixture('yuira/detail-gods-921.html')))
            ->execute(new CrawlRequestDto(url: self::URL));
        $this->movie = $this->item->meta['movie'];
    }

    public function test_url_and_external_id_are_the_decoded_product_slug(): void
    {
        self::assertSame(self::URL, $this->item->url);
        self::assertSame('ぼくと八尺様　ぽぽっとえっちなデカ尻に孕ませ', $this->movie['external_id']);
    }

    public function test_title_is_the_product_heading(): void
    {
        self::assertSame('ぼくと八尺様　ぽぽっとえっちなデカ尻に孕ませ中出し【むっちり肉厚デカ尻二穴ホール　ボトルローション】', $this->movie['title']);
    }

    public function test_code_is_the_product_code_row(): void
    {
        self::assertSame('GODS-921', $this->movie['code']);
    }

    public function test_date_is_the_release_date_as_iso(): void
    {
        self::assertSame('2026-08-31', $this->movie['date']);
    }

    public function test_duration_is_absent_because_the_page_prints_no_runtime(): void
    {
        self::assertNull($this->movie['duration']);
    }

    public function test_description_is_the_paragraphs_above_the_first_rule(): void
    {
        self::assertStringStartsWith('大きなお姉さんとのいけない夏の思い出。', $this->movie['description']);
        self::assertStringEndsWith('本体：200×250×160㎜ 商品：275×342×150㎜', $this->movie['description']);
        self::assertStringNotContainsString('メーカー', $this->movie['description']);
    }

    public function test_tags_are_the_product_categories(): void
    {
        self::assertSame(['YUIRA', 'オナホール'], $this->movie['tags']);
    }

    public function test_maker_row_is_mapped_to_metadata_maker(): void
    {
        self::assertSame('YUIRA', $this->movie['metadata']['maker']);
    }

    public function test_jan_row_is_kept_as_extra_metadata(): void
    {
        self::assertSame('4582588351010', $this->movie['metadata']['jan']);
    }

    public function test_reference_price_is_mapped_to_price_metadata(): void
    {
        self::assertSame(['amount' => 21250.0, 'currency' => 'JPY', 'label' => '参考価格'], $this->movie['metadata']['price']);
    }

    public function test_purchase_links_keep_only_buttons_with_a_target(): void
    {
        self::assertSame([
            ['name' => 'FANZAで購入する', 'url' => 'https://www.dmm.co.jp/mono/goods/-/detail/=/cid=ho12110/'],
            ['name' => 'M-ZAKKAで購入する', 'url' => 'https://mzakka.com/sp/detail/item.php?item_id=M12282'],
            ['name' => 'お店で購入する', 'url' => 'https://yuira.jp/shoplist/'],
        ], $this->movie['metadata']['purchase_links']);
    }

    public function test_source_fields_keep_every_labelled_row_with_its_raw_label(): void
    {
        self::assertSame([
            'カテゴリ' => 'YUIRA, オナホール',
            'Tag' => 'YUIRA',
            'メーカー' => 'YUIRA',
            '発売日' => '2026/8/31',
            '品番' => 'GODS-921',
            'JAN' => '4582588351010',
        ], $this->movie['metadata']['source_fields']);
    }

    public function test_cover_is_the_first_gallery_image_at_full_size(): void
    {
        self::assertSame(self::JAN_IMAGE . '00.jpg', $this->movie['cover_url']);
    }

    public function test_screenshots_are_every_gallery_image_in_page_order_with_thumbnail_pairs(): void
    {
        $shots = $this->movie['screenshots'];

        self::assertCount(4, $shots);
        self::assertSame(self::JAN_IMAGE . '00.jpg', $shots[0]['url']);
        self::assertSame(self::JAN_IMAGE . '00-600x600.jpg', $shots[0]['thumbnail_url']);
        self::assertSame(self::JAN_IMAGE . '03.jpg', $shots[1]['url']);
        self::assertSame(self::JAN_IMAGE . '03-600x600.jpg', $shots[1]['thumbnail_url']);
        self::assertSame(self::JAN_IMAGE . '02.jpg', $shots[2]['url']);
        self::assertSame(self::JAN_IMAGE . '02-600x600.jpg', $shots[2]['thumbnail_url']);
        self::assertSame(self::JAN_IMAGE . '01.jpg', $shots[3]['url']);
        self::assertSame(self::JAN_IMAGE . '01-600x600.jpg', $shots[3]['thumbnail_url']);
    }

    public function test_extracted_images_equal_the_work_images_in_the_raw_html(): void
    {
        $html = $this->loadFixture('yuira/detail-gods-921.html');
        preg_match_all('#' . preg_quote(self::JAN_IMAGE, '#') . '\d+\.jpg#', $html, $matches);
        $workImages = array_unique($matches[0]);

        self::assertCount(4, $workImages);
        self::assertCount(count($workImages), $this->movie['screenshots']);
    }

    public function test_detail_without_a_gallery_is_rejected(): void
    {
        $html = '<h2 class="product_title">Sample work</h2>';

        $this->expectException(CrawlParseException::class);

        (new Detail($this->clientWithHtml($html)))->execute(new CrawlRequestDto(url: self::URL));
    }
}
