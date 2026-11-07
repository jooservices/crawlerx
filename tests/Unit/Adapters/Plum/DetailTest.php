<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Plum;

use JOOservices\CrawlerX\Adapters\Plum\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Tests\TestCase;

final class DetailTest extends TestCase
{
    private const URL = 'https://www.plum-web.com/title/puw070/';

    private const MEDIA = 'https://www.plum-web.com/wp-content/uploads';

    /** @return array<string, mixed> */
    private function movie(): array
    {
        return (new Detail($this->clientWithFixture('plum/detail-puw070.html')))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Detail))->meta['movie'];
    }

    public function test_identity_fields(): void
    {
        $movie = $this->movie();

        self::assertSame('puw070', $movie['external_id']);
        self::assertSame('ごく普通の主婦かと甘く見てたら実はかなり下品で卑猥だった８人', $movie['title']);
        self::assertSame('PUW-070', $movie['code']);
        self::assertSame(self::MEDIA . '/package/2000/puw070_2000.jpg', $movie['cover_url']);
    }

    public function test_description_keeps_the_line_breaks_of_the_synopsis(): void
    {
        $description = $this->movie()['description'];

        self::assertStringStartsWith("昼下がりのマダムは刺激欲しさに、雄の匂い求めて街を徘徊する！\n\n①りょうこ 31歳 子供1人 T158cm B:88cm(F70) W:61cm H:97cm\n毎日疼きを", $description);
        self::assertStringEndsWith('子宮で受ける精子の感覚に思わず笑みが溢れる！', $description);
        self::assertStringNotContainsString('<br', $description);
    }

    public function test_date_duration_and_cast(): void
    {
        $movie = $this->movie();

        self::assertSame('2026-11-05', $movie['date']);
        self::assertSame(300, $movie['duration']);
        self::assertSame(
            ['村上涼子', '高瀬杏', '松下美雪', '山口翠', '三上絵理香', '美浜かおり', '仁美麗華', '成宮いろは'],
            array_column($movie['performers'], 'name'),
        );
    }

    public function test_categories_become_tags_and_links(): void
    {
        $movie = $this->movie();

        self::assertSame(['人妻', '巨乳'], $movie['tags']);
        self::assertSame([
            ['name' => '人妻', 'url' => 'https://www.plum-web.com/category/wife', 'external_id' => 'wife'],
            ['name' => '巨乳', 'url' => 'https://www.plum-web.com/category/big_tits', 'external_id' => 'big_tits'],
        ], $movie['metadata']['category_links']);
    }

    public function test_publisher_series_and_release_month(): void
    {
        $metadata = $this->movie()['metadata'];

        self::assertSame('素人onlyプラム', $metadata['maker']);
        self::assertSame('あぁ奥さん', $metadata['series']);
        self::assertSame('https://www.plum-web.com/series/puw', $metadata['series_url']);
        self::assertSame('puw', $metadata['series_id']);
        self::assertSame('https://www.plum-web.com/ym/202611', $metadata['release_month_url']);
    }

    public function test_price_review_number_and_barcode(): void
    {
        $metadata = $this->movie()['metadata'];

        self::assertSame(['amount' => 3180.0, 'currency' => 'JPY', 'label' => '¥ 3,180 （税込 ¥3,498 ）'], $metadata['price']);
        self::assertSame(3498, $metadata['price_with_tax']);
        self::assertSame('315819', $metadata['review_number']);
        self::assertSame('4589931693483', $metadata['gtin13']);
        self::assertSame(53384, $metadata['wp_post_id']);
    }

    public function test_sample_video_and_purchase_links(): void
    {
        $metadata = $this->movie()['metadata'];

        self::assertSame(self::MEDIA . '/MOV/PUW-070.mp4', $metadata['sample_video_url']);
        self::assertSame(self::MEDIA . '/package/PUW-070/PUW-070-11.jpg', $metadata['sample_video_poster_url']);
        self::assertSame([
            [
                'store' => 'fanza_buy',
                'url' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=h_113puw070/?utm_medium=dmm_affiliate&utm_source=plumweb-990&utm_campaign=affiliate_api',
                'price_label' => '￥2482',
            ],
            [
                'store' => 'fanza_st',
                'url' => 'https://al.fanza.co.jp/?lurl=https%3A%2F%2Fvideo.dmm.co.jp%2Fav%2Fcontent%2F%3Fid%3Dh_113puw00070&af_id=plumweb-990&ch=api',
                'price_label' => '￥980~',
            ],
        ], $metadata['purchase_links']);
    }

    public function test_gallery_becomes_screenshots_with_thumbnails(): void
    {
        $shots = $this->movie()['screenshots'];

        self::assertCount(20, $shots);
        self::assertSame(self::MEDIA . '/package/PUW-070/PUW-070-1.jpg', $shots[0]['url']);
        self::assertSame(self::MEDIA . '/package/PUW-070/PUW-070-1s.jpg', $shots[0]['thumbnail_url']);
        self::assertSame(1, $shots[0]['metadata']['position']);
        self::assertSame(self::MEDIA . '/package/PUW-070/PUW-070-20.jpg', $shots[19]['url']);
        self::assertSame(20, $shots[19]['metadata']['position']);
    }

    public function test_source_fields_keep_every_labelled_row_once(): void
    {
        self::assertSame([
            '作品名' => 'ごく普通の主婦かと甘く見てたら実はかなり下品で卑猥だった８人',
            'シリーズ' => 'あぁ奥さん',
            'カテゴリ' => '人妻 巨乳',
            '品番' => 'PUW-070',
            '発売日' => '2026年11月05日',
            '収録時間' => '300分',
            '価格' => '¥ 3,180 （税込 ¥3,498 ）',
            '出演女優' => '村上涼子/高瀬杏/松下美雪/山口翠/三上絵理香/美浜かおり/仁美麗華/成宮いろは',
            '審査番号' => '315819',
        ], $this->movie()['metadata']['source_fields']);
    }

    public function test_recommended_block_and_navigation_are_not_data(): void
    {
        $encoded = json_encode($this->movie(), JSON_UNESCAPED_UNICODE);

        self::assertIsString($encoded);
        self::assertStringNotContainsString('puw020', $encoded);
        self::assertStringNotContainsString('こちらもおすすめ', $encoded);
        self::assertStringNotContainsString('作品検索', $encoded);
    }

    public function test_a_blocked_page_is_reported(): void
    {
        $this->expectException(CrawlBlockedException::class);

        (new Detail($this->clientWithHtml('<html></html>', 403)))->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Detail));
    }
}
