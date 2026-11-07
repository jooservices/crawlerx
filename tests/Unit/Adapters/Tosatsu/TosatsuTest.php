<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Tosatsu;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Tosatsu\Site;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class TosatsuTest extends TestCase
{
    private const BASE = 'https://to-satsu.com';

    private const CDN = 'https://cdn.up-timely.com/image/22/content/';

    public function test_detail_exposes_every_labelled_row_and_excludes_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('tosatsu/detail-CLUB947.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/CLUB947'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/CLUB947', $result->url);
        self::assertSame('CLUB947', $movie['external_id']);
        self::assertSame('CLUB947', $movie['code']);
        self::assertSame('J系ブタ小屋連れ込みレ○プ', $movie['title']);
        self::assertStringStartsWith('男とイチャつく可愛い制服女子をつけ狙い', $movie['description']);
        self::assertStringEndsWith('ねぇねぇイオリちゃんアノ男よりボクの方がずっとイイだろう？', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(110, $movie['duration']);
        self::assertSame(self::CDN . '88457/TgPkLNrsFWORAaNHnXztpjzIRZkHM39mAM2tAlaL.jpg', $movie['cover_url']);
        self::assertCount(13, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . '88457/OLa55PQ8O3pKAskD9PVYyLWzsjBHuRokCyWcmbON.jpg', $movie['screenshots'][12]['url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['盗撮・のぞき', 'アクメ・オーガズム', '放尿・お漏らし', '中出し', '女子校生'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('TO-SATSU', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => '2026年12月1日', 'url' => self::BASE . '/works/list/date/2026-12-01', 'external_id' => '2026-12-01']], $meta['release_links']);
        self::assertSame(['5035', '9155', '115', '128', '202'], array_column($meta['genres'], 'external_id'));
        self::assertSame(['盗撮・のぞき', 'アクメ・オーガズム', '放尿・お漏らし', '中出し', '女子校生'], array_column($meta['genres'], 'name'));
        self::assertSame(self::BASE . '/works/list/genre/5035', $meta['genres'][0]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'CLUB947', 'duration' => 110, 'price' => '4,200円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/club00947dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . '88457/sample_video_image_file/Wj0HdzSrvvRFW5BOb1aglMoT5YYJOSl8FslMsdbS.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=club00947', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=club947', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['発売日', 'シリーズ', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('発売日 2026年12月1日', $meta['source_fields']['発売日']);
        self::assertSame('品番 DVDCLUB947', $meta['source_fields']['品番']);
        self::assertSame('収録時間 DVD110分', $meta['source_fields']['収録時間']);
        self::assertSame('価格 DVD4,200円（税込）', $meta['source_fields']['価格']);
        self::assertStringNotContainsString('CLUB945', json_encode($movie, JSON_THROW_ON_ERROR));
    }

    public function test_second_detail_with_director_placeholder_and_longer_gallery(): void
    {
        $result = (new Detail($this->clientWithFixture('tosatsu/detail-STOL158.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/STOL158'));
        $movie = $result->meta['movie'];
        self::assertSame('STOL158', $movie['code']);
        self::assertSame('ヌキなし健全店の裏側で生ハメ中出しを求める発情セラピスト 西麻布某高級メンズエステ盗撮', $movie['title']);
        self::assertStringStartsWith('【美人淫乱セラピストとの邂逅】', $movie['description']);
        self::assertStringEndsWith('確信犯セラピストの神対応＆卑猥施術。', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(220, $movie['duration']);
        self::assertCount(19, $movie['screenshots']);
        self::assertSame(['エステ', '中出し', '巨乳', 'ベスト・総集編', '盗撮・のぞき'], $movie['tags']);
        self::assertSame([], $movie['performers']);
        $meta = $movie['metadata'];
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['format' => 'DVD', 'code' => 'STOL158', 'duration' => 220, 'price' => '4,200円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/stol00158dm.mp4', $meta['sample_video_url']);
        self::assertSame('監督 ---', $meta['source_fields']['監督']);
        self::assertCount(7, $meta['source_fields']);
    }

    public function test_date_listing_cards_without_actress_pages(): void
    {
        $result = (new Listing($this->clientWithFixture('tosatsu/list-2026-12-01.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-12-01'));
        self::assertCount(3, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/CLUB947', $first->url);
        self::assertSame('CLUB947', $movie['external_id']);
        self::assertSame('CLUB947', $movie['code']);
        self::assertSame('J系ブタ小屋連れ込みレ○プ', $movie['title']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(self::CDN . '88457/IAjlZx3EYK0yvutqsbc7nXyFw7IkJ3ojsgSNecV9.jpg', $movie['cover_url']);
        self::assertSame([], $movie['performers']);
        self::assertSame('TO-SATSU', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年12月1日', $movie['metadata']['search_key']);
        self::assertSame('全3作品中 1 〜 3 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(['CLUB947', 'CLUB950', 'STOL158'], array_map(static fn($item) => $item->meta['movie']['code'], $result->items));
        self::assertSame('一流ホテルウーマン 上品おま●こ激ピストン中出しレ●プ乱交映像', $result->items[1]->meta['movie']['title']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_date_index_follows_first_release_group_and_paginates(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('tosatsu/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('tosatsu/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(3, $result->items);
        $group = $result->items[0]->meta['movie']['metadata']['release_group'];
        self::assertSame('2026-12-01', $group['date']);
        self::assertSame(3, $group['count']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $group['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(81, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }
}
