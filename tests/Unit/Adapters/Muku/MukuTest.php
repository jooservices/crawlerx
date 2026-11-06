<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Muku;

use JOOservices\CrawlerX\Adapters\Muku\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class MukuTest extends TestCase
{
    private const BASE = 'https://muku.tv';

    public function test_detail_without_actress_extracts_every_field_and_excludes_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('muku/detail-MUDR408.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/MUDR408'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MUDR408', $result->url);
        self::assertSame('MUDR408', $movie['external_id']);
        self::assertSame('MUDR408', $movie['code']);
        self::assertSame('【アナル渇望】　ハスキーボイスの敏感デカクリ　ボーイッシュガールが初めてのアナルSEXでイキまくる！', $movie['title']);
        self::assertStringStartsWith('2本目にして早くもアナルＳＥＸ解禁！', $movie['description']);
        self::assertStringEndsWith('ボーイッシュ少女アナル渇望SEXドキュメント！', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/14/content/88203/pC0IB6ioW6I2u7bDp3WrbHcVwaYGTBHrV6bMUuhE.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/14/content/88203/ELZpGvz2SZIi8UcR7Hepq1RcuSMAVgOKrwl09eIm.jpg', $movie['screenshots'][10]['url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['3P、4P', 'アナル', '中出し', '貧乳・微乳', 'アナルセックス'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('MUKU', $meta['maker']);
        self::assertSame(['421', '129', '128', '5015', '482'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/421', $meta['genres'][0]['url']);
        self::assertSame('3P、4P', $meta['genres'][0]['name']);
        self::assertSame([], $meta['series']);
        self::assertNull($meta['director']);
        self::assertArrayNotHasKey('label', $meta);
        self::assertSame([['name' => '2026年11月17日', 'url' => self::BASE . '/works/list/date/2026-11-17', 'external_id' => '2026-11-17']], $meta['release_links']);
        self::assertSame([['format' => 'DVD', 'code' => 'MUDR408', 'duration' => 120, 'price' => '4,598円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/mudr00408dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/eE4WCUQVQTX1YwbFv6vx5xUB1IrMEl00zytVD1aR.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=mudr00408', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=mudr408', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('発売日 2026年11月17日', $meta['source_fields']['発売日']);
        self::assertSame('価格 DVD4,598円（税込）', $meta['source_fields']['価格']);
        foreach ($movie['screenshots'] as $image) {
            self::assertStringNotContainsString('MUKC133', $image['url']);
        }
    }

    public function test_detail_with_actress_extracts_performer_and_basic_fields(): void
    {
        $result = (new Detail($this->clientWithFixture('muku/detail-MUKC133.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/MUKC133'));
        $movie = $result->meta['movie'];
        self::assertSame('MUKC133', $movie['external_id']);
        self::assertSame('清楚なんて嘘でしょ？結局ち●ぽに勝てないくせに。 健全売り美少女コスプレイヤー　即ズボ快楽堕ち。完全キャラ崩壊。', $movie['title']);
        self::assertStringStartsWith('「私は健全売りのコスプレイヤーなので', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(160, $movie['duration']);
        self::assertCount(18, $movie['screenshots']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('870989', $movie['performers'][0]['external_id']);
        self::assertSame('釈アリス', $movie['performers'][0]['name']);
        self::assertSame('釈アリス', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/870989', $movie['performers'][0]['url']);
        self::assertSame(['美少女', 'アクメ・オーガズム', '中出し', '3P、4P', 'コスプレ'], $movie['tags']);
        self::assertSame([['format' => 'DVD', 'code' => 'MUKC133', 'duration' => 160, 'price' => '3,498円（税込）']], $movie['metadata']['formats']);
        self::assertSame('女優 釈アリス', $movie['metadata']['source_fields']['女優']);
        self::assertCount(8, $movie['metadata']['source_fields']);
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('muku/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('muku/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(14, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MUDR408', $first->url);
        self::assertSame('MUDR408', $movie['external_id']);
        self::assertSame('MUDR408', $movie['code']);
        self::assertSame('【アナル渇望】　ハスキーボイスの敏感デカクリ　ボーイッシュガールが初めてのアナルSEXでイキまくる！', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/14/content/88203/wGUb2VLjhX9E5fH6IJ3USbK9h4PrU6Vq6LbXV87q.jpg', $movie['cover_url']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame('MUKU', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame(14, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-17', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-17', $movie['metadata']['release_group']['url']);
        self::assertSame('全14作品中 1 〜 14 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('MUCD368', $result->items[13]->meta['movie']['code']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(93, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('muku/list-2026-11-17.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-17'));
        self::assertCount(14, $result->items);
        self::assertSame('2026-11-17', $result->items[0]->meta['movie']['date']);
        self::assertSame('発売日検索結果：2026年11月17日', $result->items[0]->meta['movie']['metadata']['search_key']);
        self::assertSame('全14作品中 1 〜 14 タイトルを表示', $result->items[0]->meta['movie']['metadata']['listing_range']);
        self::assertNull($result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
    }
}
