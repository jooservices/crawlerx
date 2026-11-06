<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Rookie;

use JOOservices\CrawlerX\Adapters\Rookie\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class RookieTest extends TestCase
{
    private const BASE = 'https://rookie-av.jp';

    public function test_detail_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('rookie/detail-RKI773.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/RKI773'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/RKI773', $result->url);
        self::assertSame('RKI773', $movie['external_id']);
        self::assertSame('RKI773', $movie['code']);
        self::assertSame('おいっビッチ女こっち来い！　みんなで輪姦中出しして制服美女を犯し尽くして精神ズタボロにする', $movie['title']);
        self::assertStringStartsWith('＃登下校をストーキング＃パンチラ盗撮', $movie['description']);
        self::assertStringEndsWith('＃レイプで堕ちた女は俺らの言いなり', $movie['description']);
        self::assertSame('2026-11-10', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/26/content/88111/HPLjVkcZi7TwQK4P7VQx9sTB0ag9G2qmzXH20NCO.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/26/content/88111/vv9xkkzeAm3YBVjDPJEQ2DPKYbRUfWn9focpemEB.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('851176', $movie['performers'][0]['external_id']);
        self::assertSame('花守夏歩', $movie['performers'][0]['name']);
        self::assertSame('花守夏歩', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/851176', $movie['performers'][0]['url']);
        self::assertSame(['中出し', 'イラマチオ', '淫乱・ハード系', 'フェラ', '凌辱'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('ROOKIE', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => '2026年11月10日', 'url' => self::BASE . '/works/list/date/2026-11-10', 'external_id' => '2026-11-10']], $meta['release_links']);
        self::assertSame(['128', '459', '411', '113', '132'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/128', $meta['genres'][0]['url']);
        self::assertSame('中出し', $meta['genres'][0]['name']);
        self::assertSame([['format' => 'DVD', 'code' => 'RKI773', 'duration' => 120, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/rki00773dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/kbT6ofP4LTusB3YSmWQ7uAZLdKV5n3QIdregDIvi.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=rki00773', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=rki773', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertCount(8, $meta['source_fields']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('女優 花守夏歩', $meta['source_fields']['女優']);
        self::assertSame('発売日 2026年11月10日', $meta['source_fields']['発売日']);
        self::assertSame('シリーズ', $meta['source_fields']['シリーズ']);
        self::assertSame('ジャンル 中出し イラマチオ 淫乱・ハード系 フェラ 凌辱', $meta['source_fields']['ジャンル']);
        self::assertSame('監督', $meta['source_fields']['監督']);
        self::assertSame('品番 DVDRKI773', $meta['source_fields']['品番']);
        self::assertSame('収録時間 DVD120分', $meta['source_fields']['収録時間']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        foreach ($movie['screenshots'] as $image) {
            self::assertStringNotContainsString('82177', $image['url']);
        }
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('rookie/dates.html')],
            self::BASE . '/works/list/date/2026-11-10' => ['body' => $this->loadFixture('rookie/list-2026-11-10.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(3, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/RKI773', $first->url);
        self::assertSame('RKI773', $movie['external_id']);
        self::assertSame('RKI773', $movie['code']);
        self::assertSame('おいっビッチ女こっち来い！　みんなで輪姦中出しして制服美女を犯し尽くして精神ズタボロにする', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/26/content/88111/Pd3hK2WNgBEPTfgu4lWqjaXwfF9i6xwEXpSw5zsy.jpg', $movie['cover_url']);
        self::assertSame('2026-11-10', $movie['date']);
        self::assertSame('851176', $movie['performers'][0]['external_id']);
        self::assertSame('花守夏歩', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/actress/detail/851176', $movie['performers'][0]['url']);
        self::assertSame('ROOKIE', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame(3, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-10', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-10', $movie['metadata']['release_group']['url']);
        self::assertSame('発売日検索結果：2026年11月10日', $movie['metadata']['search_key']);
        self::assertSame('全3作品中 1 〜 3 タイトルを表示', $movie['metadata']['listing_range']);
        $ensemble = $result->items[2]->meta['movie'];
        self::assertSame('RBB348', $ensemble['code']);
        self::assertSame(10, $ensemble['metadata']['performer_count']);
        self::assertSame([], $ensemble['performers']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(95, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('rookie/list-2026-10-13.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-10-13'));
        self::assertCount(6, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('RKI770', $movie['code']);
        self::assertSame('この恋に気づいてくれた', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/26/content/87632/jjmkaSMWIr0FkPHs4BTH8wz2J5TkAKIoaJhLJX3V.jpg', $movie['cover_url']);
        self::assertSame('2026-10-13', $movie['date']);
        self::assertSame('769561', $movie['performers'][0]['external_id']);
        self::assertSame('発売日検索結果：2026年10月13日', $movie['metadata']['search_key']);
        self::assertSame('全6作品中 1 〜 6 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('rookie/actress-851176.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/851176'));
        $person = $result->meta['performer'];
        self::assertSame('851176', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/851176', $person['url']);
        self::assertSame('HANAMORI KAHO', $person['name']);
        self::assertSame('花守夏歩', $person['name_japanese']);
        self::assertSame(['花守夏歩', 'HANAMORI KAHO'], $person['aliases']);
        self::assertNull($person['height_raw']);
        self::assertNull($person['size_raw']);
        self::assertNull($person['birth_date_raw']);
        self::assertSame([], $person['raw_profile']);
        self::assertSame('https://cdn.up-timely.com/image/26/actress_main/851176/jvNgTBg9Ut2GVnkKshrGn4V85ay3IgzVaq9MsE7s.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertCount(1, $person['metadata']['works']);
        self::assertSame('RKI773', $person['metadata']['works'][0]['external_id']);
        self::assertSame('おいっビッチ女こっち来い！　みんなで輪姦中出しして制服美女を犯し尽くして精神ズタボロにする', $person['metadata']['works'][0]['name']);
        self::assertSame('https://cdn.up-timely.com/image/26/content/88111/Pd3hK2WNgBEPTfgu4lWqjaXwfF9i6xwEXpSw5zsy.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertStringContainsString('/works/detail/RKI773', $person['metadata']['works'][0]['url']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }
}
