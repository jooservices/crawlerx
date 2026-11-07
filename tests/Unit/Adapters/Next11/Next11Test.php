<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Next11;

use JOOservices\CrawlerX\Adapters\Next11\Next11Crawler;
use JOOservices\CrawlerX\Adapters\Next11\Types\Detail;
use JOOservices\CrawlerX\Adapters\Next11\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class Next11Test extends TestCase
{
    private const BASE = 'https://next11.co.jp';

    /** @return array<string, mixed> */
    private function detail(string $fixture, string $id): array
    {
        return (new Detail($this->clientWithFixture('next11/' . $fixture)))
            ->execute(new CrawlRequestDto(url: self::BASE . '/products/detail.php?product_id=' . $id, type: CrawlType::Detail))
            ->meta['movie'];
    }

    public function test_detail_fields_from_real_capture(): void
    {
        $movie = $this->detail('detail-KIR-080.html', '13788');

        self::assertSame('13788', $movie['external_id']);
        self::assertSame('KIR-080', $movie['code']);
        self::assertStringStartsWith('「続きは家でしましょうか…」残業終わり', $movie['title']);
        self::assertStringEndsWith('連続中出し　澄河美花', $movie['title']);
        self::assertStringStartsWith('巨乳女上司に弄ばれて理性崩壊!', $movie['description']);
        self::assertStringEndsWith('社内SEXで何度も中出し!!', $movie['description']);
        self::assertSame('2026-10-20', $movie['date']);
        self::assertSame(130, $movie['duration']);
        self::assertSame(['巨乳', '職業', '中出し', 'AV女優', '尻'], $movie['tags']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('4652', $movie['performers'][0]['external_id']);
        self::assertSame('澄河美花', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/products/list.php?category_id=4652', $movie['performers'][0]['url']);
    }

    public function test_detail_images_cover_every_sample_still_with_thumbnails(): void
    {
        $movie = $this->detail('detail-KIR-080.html', '13788');

        self::assertSame(self::BASE . '/upload/save_image/jacket/KIR-080.jpg', $movie['cover_url']);
        self::assertSame(self::BASE . '/upload/save_image/jacket/m_KIR-080.jpg', $movie['metadata']['cover_medium_url']);
        self::assertCount(6, $movie['screenshots']);
        self::assertSame(self::BASE . '/upload/save_image/photo/ph01kir-080.jpg', $movie['screenshots'][0]['url']);
        self::assertSame(self::BASE . '/upload/save_image/photo/s_ph01kir-080.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame(self::BASE . '/upload/save_image/photo/ph06kir-080.jpg', $movie['screenshots'][5]['url']);
        self::assertSame(self::BASE . '/upload/save_image/photo/s_ph06kir-080.jpg', $movie['screenshots'][5]['thumbnail_url']);
        self::assertSame(6, $movie['screenshots'][5]['metadata']['position']);
        self::assertStringStartsWith('https://www.dmm.co.jp/litevideo/-/part/=/cid=h_1664kir080/', $movie['metadata']['sample_video_url']);
    }

    public function test_detail_metadata_from_real_capture_excludes_recommendations(): void
    {
        $meta = $this->detail('detail-KIR-080.html', '13788')['metadata'];

        self::assertSame('ネクストグループ', $meta['maker']);
        self::assertSame('KIRei', $meta['label']);
        self::assertSame('4343', $meta['label_id']);
        self::assertSame(self::BASE . '/products/list.php?category_id=4343', $meta['label_url']);
        self::assertSame(['35', '43', '53', '83', '84'], array_column($meta['genres'], 'external_id'));
        self::assertSame('巨乳', $meta['genres'][0]['name']);
        self::assertSame(self::BASE . '/products/list.php?category_id=35', $meta['genres'][0]['url']);
        self::assertSame(['NEW', 'オススメ'], $meta['badges']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('dvd', $meta['purchase_links'][0]['type']);
        self::assertSame('DVDを購入', $meta['purchase_links'][0]['label']);
        self::assertStringContainsString('cid%3Dh_1664kir080', $meta['purchase_links'][0]['url']);
        self::assertSame('video', $meta['purchase_links'][1]['type']);
        self::assertSame('ダウンロード+ストリーミング', $meta['purchase_links'][1]['note']);
        self::assertSame('130分', $meta['source_fields']['収録時間']);
        self::assertSame('2026-10-20', $meta['source_fields']['発売日']);
        self::assertSame('KIR-080', $meta['source_fields']['品番']);
        self::assertSame('澄河美花', $meta['source_fields']['出演']);
        self::assertSame('KIRei', $meta['source_fields']['レーベル']);
        self::assertSame('巨乳, 職業, 中出し, AV女優, 尻', $meta['source_fields']['ジャンル']);
        self::assertSame(7, count($meta['source_fields']));
        self::assertStringNotContainsString('KIR-082', (string) json_encode($meta));
    }

    public function test_detail_without_cast_has_series_and_pre_release_note(): void
    {
        $movie = $this->detail('detail-KANZ-354.html', '13814');

        self::assertSame('KANZ-354', $movie['code']);
        self::assertSame('性とエロスの記憶～昭和官能コレクション 9時間完全盤６枚組', $movie['title']);
        self::assertSame('2026-11-20', $movie['date']);
        self::assertSame(540, $movie['duration']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['複数枚組', '4時間以上作品', '人妻', 'ベスト・総集編', '大容量DVD', '寝取り・寝取られ'], $movie['tags']);
        self::assertSame(self::BASE . '/upload/save_image/jacket/KANZ-354.jpg', $movie['cover_url']);
        self::assertCount(6, $movie['screenshots']);
        self::assertStringStartsWith('https://www.dmm.co.jp/litevideo/-/part/=/cid=h_1664kanz354/', $movie['metadata']['sample_video_url']);
        self::assertSame('完全盤6枚組', $movie['metadata']['series']);
        self::assertSame('2751', $movie['metadata']['series_id']);
        self::assertSame(self::BASE . '/products/list.php?category_id=2751', $movie['metadata']['series_url']);
        self::assertSame('発売日前の商品です。2026年11月20日発売 !', $movie['metadata']['release_note']);
        self::assertSame(['NEW', 'オススメ', '予約'], $movie['metadata']['badges']);
        self::assertArrayNotHasKey('label', $movie['metadata']);
        self::assertSame('完全盤6枚組', $movie['metadata']['source_fields']['シリーズ']);
    }

    public function test_month_listing_cards_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('next11/list-ym-202611.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/products/list.php?ym=202611', type: CrawlType::Listing));

        self::assertSame('movie', $result->entityType);
        self::assertCount(15, $result->items);
        $first = $result->items[0];
        self::assertSame(self::BASE . '/products/detail.php?product_id=13812', $first->url);
        $movie = $first->meta['movie'];
        self::assertSame('13812', $movie['external_id']);
        self::assertSame('「挿入れちゃダメ・・・」還暦の母がエロすぎて中出しSEXしてしまった2枚組', $movie['title']);
        self::assertSame('COU-023', $movie['code']);
        self::assertSame(self::BASE . '/upload/save_image/jacket/m_COU-023.jpg', $movie['cover_url']);
        self::assertSame('4325', $movie['performers'][0]['external_id']);
        self::assertSame('杉本秀美', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/products/list.php?category_id=4325', $movie['performers'][0]['url']);
        self::assertSame('発売日前の商品です。', $movie['metadata']['release_note']);
        self::assertTrue($movie['metadata']['pre_release']);
        self::assertSame(['女優' => '杉本秀美', '商品' => 'COU-023'], $movie['metadata']['fields']);
        self::assertSame('YOZ-397', $result->items[14]->meta['movie']['code']);
        self::assertSame([], $result->items[14]->meta['movie']['performers']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertStringNotContainsString('13788', (string) json_encode($result->items));
    }

    public function test_label_listing_paginates_and_marks_released_cards(): void
    {
        $result = (new Listing($this->clientWithFixture('next11/list-label-4343.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/products/list.php?category_id=4343', type: CrawlType::Listing));

        self::assertCount(30, $result->items);
        $released = $result->items[2]->meta['movie'];
        self::assertSame('KIR-080', $released['code']);
        self::assertArrayNotHasKey('release_note', $released['metadata']);
        self::assertArrayNotHasKey('pre_release', $released['metadata']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(3, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/products/list.php?category_id=4343&pageno=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('next11');
        self::assertInstanceOf(Next11Crawler::class, $crawler);
        $expected = [
            '/products/detail.php?product_id=13788' => CrawlType::Detail,
            '/products/list.php?category_id=4343' => CrawlType::Listing,
            '/products/list.php?category_id=4343&pageno=2' => CrawlType::Listing,
            '/products/list.php?ym=202611' => CrawlType::Listing,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/products/detail.php')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/user_data/ranking.php')->matched);
        self::assertSame('next11', $crawler->name());
    }

    public function test_listing_page_follows_the_pageno_query_parameter(): void
    {
        $result = (new Listing($this->clientWithFixture('next11/list-label-4343.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/products/list.php?category_id=4343&pageno=2', type: CrawlType::Listing));

        self::assertSame(2, $result->page);
    }

    public function test_labels_ending_in_a_multibyte_character_keep_their_last_character(): void
    {
        $rows = (new \JOOservices\CrawlerX\Adapters\Next11\Fields())->rows(
            new \Symfony\Component\DomCrawler\Crawler('<dl><dt>ブレンダー：</dt><dd>x</dd><dt>メーカー:</dt><dd>y</dd></dl>'),
        );

        self::assertSame(['ブレンダー', 'メーカー'], array_keys($rows));
    }
}
