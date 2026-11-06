<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Miman;

use JOOservices\CrawlerX\Adapters\Miman\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class MimanTest extends TestCase
{
    private const BASE = 'https://miman.jp';

    public function test_detail_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('miman/detail-MMXD035.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/MMXD035'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MMXD035', $result->url);
        self::assertSame('MMXD035', $movie['external_id']);
        self::assertSame('MMXD035', $movie['code']);
        self::assertSame('未満、最後の着衣いたずら 制服おっぱい少女のラストベスト作品49人8時間 完全穢し撮り', $movie['title']);
        self::assertStringStartsWith('【純真無垢な巨乳美少女を穢す至高の背徳感】全国の未満ファンの皆様', $movie['description']);
        self::assertStringEndsWith('ラストに相応しい超傑作になっております。是非ご堪能ください。', $movie['description']);
        self::assertSame('2024-01-02', $movie['date']);
        self::assertSame(470, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/9/content/71630/lriG6qUcXfqPC2E2Q78GBALMruOugT7eTdh2F4ZS.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/9/content/71630/xvtDh10vT6YSXkSfPkmXzlIXZfutcyd96h1rZDo7.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(24, $movie['performers']);
        self::assertSame(['200475', '201028', '201307', '201510', '201643', '190355', '201621', '202717', '202449', '202916', '203031', '203023', '203713', '202113', '203841', '203854', '766215', '766219', '796787', '797770', '798036', '801205', '803719', '808853'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['水卜さくら', '百々谷りあ', '伊藤舞雪', '小栗もなか', '雛鶴みお', 'JULIA', 'あやみ旬果', '柊るい', '稲場るか', '御坂恵衣', '佐藤りこ', '佐知子', '露梨あやせ', '深田えいみ', '神坂朋子', '天音まひな', '淡月みたま', '凪沙ゆきの', '長谷川古宵', '初愛ねんね', '舞奈みく', 'ほむら優音', '蒼井りあん', '小倉七海'], array_column($movie['performers'], 'name'));
        self::assertSame($movie['performers'][0]['name'], $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/200475', $movie['performers'][0]['url']);
        self::assertSame(['制服', '巨乳', '美少女', 'ベスト・総集編', 'イメージビデオ'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('MIMAN', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([['name' => '未満ベスト', 'url' => self::BASE . '/works/list/series/1544', 'external_id' => '1544']], $meta['series']);
        self::assertArrayNotHasKey('label', $meta);
        self::assertSame([['name' => '2024年1月2日', 'url' => self::BASE . '/works/list/date/2024-01-02', 'external_id' => '2024-01-02']], $meta['release_links']);
        self::assertSame(['336', '215', '330', '5009', '312'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/336', $meta['genres'][0]['url']);
        self::assertSame('制服', $meta['genres'][0]['name']);
        self::assertSame([['format' => 'DVD', 'code' => 'MMXD035', 'duration' => 470, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/litevideo/freepv/m/mmx/mmxd00035/mmxd00035_dm_w.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/zjem6haANlIbIHBfS7rNuFlxpHupgNLdWXOFQ1OH.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=mmxd00035', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=mmxd035', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        self::assertSame('シリーズ 未満ベスト', $meta['source_fields']['シリーズ']);
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('miman/dates.html')],
            self::BASE . '/works/list/date/2024-01-02' => ['body' => $this->loadFixture('miman/list-2024-01-02.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(1, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MMXD035', $first->url);
        self::assertSame('MMXD035', $movie['external_id']);
        self::assertSame('MMXD035', $movie['code']);
        self::assertSame('未満、最後の着衣いたずら 制服おっぱい少女のラストベスト作品49人8時間 完全穢し撮り', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/9/content/71630/1w7jH71w12SztfIBOT9ZqS825qKRZ4mVXwLGdnsI.jpg', $movie['cover_url']);
        self::assertSame('2024-01-02', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame('MIMAN', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertSame(24, $movie['metadata']['performer_count']);
        self::assertSame(1, $movie['metadata']['release_group']['count']);
        self::assertSame('2024-01-02', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2024-01-02', $movie['metadata']['release_group']['url']);
        self::assertSame('発売日検索結果：2024年1月2日', $movie['metadata']['search_key']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(28, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('miman/list-2024-01-02.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2024-01-02'));
        self::assertCount(1, $result->items);
        self::assertSame('MMXD035', $result->items[0]->meta['movie']['code']);
        self::assertSame('2024-01-02', $result->items[0]->meta['movie']['date']);
        self::assertNull($result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
    }
}
