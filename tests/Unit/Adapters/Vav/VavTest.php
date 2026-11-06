<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Vav;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Vav\Site;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class VavTest extends TestCase
{
    private const BASE = 'https://v-av.com';

    private const CDN = 'https://cdn.up-timely.com/image/21/content/';

    public function test_detail_without_actress_has_every_field_and_no_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('vav/detail-VVVD205.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/VVVD205'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/VVVD205', $result->url);
        self::assertSame('VVVD205', $movie['external_id']);
        self::assertSame('VVVD205', $movie['code']);
        self::assertSame('女監督 伊達彩華が自ら裸になって勝手にレズ隠し撮り！そのまま無断でAV発売！Vol.1～9全作品集', $movie['title']);
        self::assertStringStartsWith('女子2人だけの密室だから撮れるレズハメ！', $movie['description']);
        self::assertStringEndsWith('素人娘たちが快楽に堕ちる瞬間をトコトンお楽しみ下さい。', $movie['description']);
        self::assertSame('2022-05-03', $movie['date']);
        self::assertSame(240, $movie['duration']);
        self::assertSame(self::CDN . '59088/VVVD205_2.jpg', $movie['cover_url']);
        self::assertSame([['url' => self::CDN . '59088/VVVD205_2.jpg']], array_map(static fn(array $image): array => ['url' => $image['url']], $movie['screenshots']));
        self::assertSame([], $movie['performers']);
        self::assertSame(['レズ', '素人', '潮吹き', 'アナル', 'ベスト・総集編'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('V-AV', $meta['maker']);
        self::assertSame('伊達彩華', $meta['director']);
        self::assertSame([['name' => '神BEST', 'url' => self::BASE . '/works/list/series/2992', 'external_id' => '2992']], $meta['series']);
        self::assertSame([['name' => 'Ｖ', 'url' => self::BASE . '/works/list/label/6177', 'external_id' => '6177']], $meta['label']);
        self::assertSame([['name' => '2022年5月3日', 'url' => self::BASE . '/works/list/date/2022-05-03', 'external_id' => '2022-05-03']], $meta['release_links']);
        self::assertSame(['111', '207', '114', '129', '5009'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/111', $meta['genres'][0]['url']);
        self::assertSame('レズ', $meta['genres'][0]['name']);
        self::assertSame([['format' => 'DVD', 'code' => 'VVVD205', 'duration' => 240, 'price' => '3,278円（税込）']], $meta['formats']);
        self::assertNull($meta['sample_video_url']);
        self::assertNull($meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=vvvd00205/WILLaffi-054', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertSame('https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=vvvd205/WILLaffi-054', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'レーベル', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('監督 伊達彩華', $meta['source_fields']['監督']);
        self::assertSame('価格 DVD3,278円（税込）', $meta['source_fields']['価格']);
        self::assertStringNotContainsString('VVVD194', json_encode($movie, JSON_THROW_ON_ERROR));
    }

    public function test_detail_keeps_unlinked_actress_name_and_empty_director(): void
    {
        $result = (new Detail($this->clientWithFixture('vav/detail-VVVD182.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/VVVD182'));
        $movie = $result->meta['movie'];
        self::assertSame('VVVD182', $movie['code']);
        self::assertSame('神尻と肛門を限界まで8時間調教BEST', $movie['title']);
        self::assertStringStartsWith('神ユキのアナルと美尻を限界まで凌辱しまくる8時間BEST！', $movie['description']);
        self::assertSame('2021-02-01', $movie['date']);
        self::assertSame(480, $movie['duration']);
        self::assertSame(self::CDN . '53233/VVVD182_2.jpg', $movie['cover_url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('神ユキ', $movie['performers'][0]['name']);
        self::assertSame('神ユキ', $movie['performers'][0]['name_japanese']);
        self::assertNull($movie['performers'][0]['url']);
        self::assertNull($movie['performers'][0]['external_id']);
        self::assertSame(['アナル', '巨尻', 'SM', 'レズ', '女優物'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertNull($meta['director']);
        self::assertSame('2021-02-01', $meta['release_links'][0]['external_id']);
        self::assertSame('2992', $meta['series'][0]['external_id']);
        self::assertSame('6177', $meta['label'][0]['external_id']);
        self::assertSame(['129', '484', '110', '111', '211'], array_column($meta['genres'], 'external_id'));
        self::assertSame([['format' => 'DVD', 'code' => 'VVVD182', 'duration' => 480, 'price' => '3,278円（税込）']], $meta['formats']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame('女優 神ユキ', $meta['source_fields']['女優']);
        self::assertSame('監督 ---', $meta['source_fields']['監督']);
        self::assertCount(9, $meta['source_fields']);
    }

    public function test_date_index_listing_card_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('vav/dates.html')],
            self::BASE . '/works/list/date/2022-05-03' => ['body' => $this->loadFixture('vav/list-2022-05-03.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(1, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/VVVD205', $first->url);
        self::assertSame('VVVD205', $movie['external_id']);
        self::assertSame('VVVD205', $movie['code']);
        self::assertSame('女監督 伊達彩華が自ら裸になって勝手にレズ隠し撮り！そのまま無断でAV発売！Vol.1～9全作品集', $movie['title']);
        self::assertSame(self::CDN . '59088/VVVD205_1.jpg', $movie['cover_url']);
        self::assertSame('2022-05-03', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame('V-AV', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame(1, $movie['metadata']['release_group']['count']);
        self::assertSame('2022-05-03', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2022-05-03', $movie['metadata']['release_group']['url']);
        self::assertSame('発売日検索結果：2022年5月3日', $movie['metadata']['search_key']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(15, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_card_keeps_unlinked_actress(): void
    {
        $result = (new Listing($this->clientWithFixture('vav/list-2021-02-01.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2021-02-01'));
        self::assertCount(1, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('VVVD182', $movie['code']);
        self::assertSame('神尻と肛門を限界まで8時間調教BEST', $movie['title']);
        self::assertSame(self::CDN . '53233/VVVD182_1.jpg', $movie['cover_url']);
        self::assertSame('2021-02-01', $movie['date']);
        self::assertSame(['神ユキ'], array_column($movie['performers'], 'name'));
        self::assertNull($movie['performers'][0]['url']);
        self::assertSame('V-AV', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2021年2月1日', $movie['metadata']['search_key']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertFalse($result->pagination->hasNextPage);
    }
}
