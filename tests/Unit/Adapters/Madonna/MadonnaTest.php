<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Madonna;

use JOOservices\CrawlerX\Adapters\Madonna\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class MadonnaTest extends TestCase
{
    private const BASE = 'https://madonna-av.com';

    private const CDN = 'https://cdn.up-timely.com/image/10/content/88077/';

    public function test_detail_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('madonna/detail-JUR920.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/JUR920'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/JUR920', $result->url);
        self::assertSame('JUR920', $movie['external_id']);
        self::assertSame('JUR920', $movie['code']);
        self::assertSame('超大型専属、遂に《中出し》解禁ー！！ 夫と子作りSEXをした後はいつも義父に中出しされ続けています…。', $movie['title']);
        self::assertStringStartsWith('結婚して4年、誰も住んでいないという夫の実家に引っ越して', $movie['description']);
        self::assertStringEndsWith('その姿を義父に見られてしまって…。', $movie['description']);
        self::assertSame('2026-11-10', $movie['date']);
        self::assertSame(140, $movie['duration']);
        self::assertSame(self::CDN . '3vixoOWatI2EKwN1uatfxkxxRlfoWGUkhWNeP0F3.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . 'lHDqGVp9SFhSulb8pUviICwjcDxTuKZY5q3ktHaJ.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('872972', $movie['performers'][0]['external_id']);
        self::assertSame('山田ゆり', $movie['performers'][0]['name']);
        self::assertSame('山田ゆり', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/872972', $movie['performers'][0]['url']);
        self::assertSame(['中出し', 'キス・接吻', 'ドラマ', '熟女', '人妻・主婦'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('Madonna', $meta['maker']);
        self::assertSame('古賀丈士', $meta['director']);
        self::assertSame([['name' => '夫と子作りSEXをした後はいつも義父に中出しされ続けています…', 'url' => self::BASE . '/works/list/series/1729', 'external_id' => '1729']], $meta['series']);
        self::assertSame([['name' => 'Madonna', 'url' => self::BASE . '/works/list/label/3669', 'external_id' => '3669']], $meta['label']);
        self::assertSame([['name' => '2026年11月10日', 'url' => self::BASE . '/works/list/date/2026-11-10', 'external_id' => '2026-11-10']], $meta['release_links']);
        self::assertSame(['128', '9163', '447', '206', '205'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/128', $meta['genres'][0]['url']);
        self::assertSame('中出し', $meta['genres'][0]['name']);
        self::assertSame([['format' => 'DVD', 'code' => 'JUR920', 'duration' => 140, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/jur00920dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/OtP3rCOOidUhyf6g2GTKGBvCKsTjll4tVtvPmmRw.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=jur00920', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=jur920', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertCount(9, $meta['source_fields']);
        self::assertSame('レーベル Madonna', $meta['source_fields']['レーベル']);
        self::assertSame('品番 DVDJUR920', $meta['source_fields']['品番']);
        self::assertSame('収録時間 DVD140分', $meta['source_fields']['収録時間']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        foreach ($movie['screenshots'] as $image) {
            self::assertStringContainsString('/content/88077/', $image['url']);
        }
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('madonna/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('madonna/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(1, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/' . $movie['code'], $result->items[0]->url);
        self::assertSame($movie['code'], $movie['external_id']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertNotEmpty($movie['title']);
        self::assertNotEmpty($movie['cover_url']);
        self::assertSame('Madonna', $movie['metadata']['maker']);
        self::assertSame(1, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-12-01', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $movie['metadata']['release_group']['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(412, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_cards_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('madonna/list-2026-11-10.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-10'));
        self::assertCount(30, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/JUR920', $first->url);
        self::assertSame('JUR920', $movie['external_id']);
        self::assertSame('JUR920', $movie['code']);
        self::assertSame('夫と子作りSEXをした後はいつも義父に中出しされ続けています…。', $movie['title']);
        self::assertSame(self::CDN . 'w3UUunC249tldl4v3tQL1jxVG2cfpFSaHYTzqgxn.jpg', $movie['cover_url']);
        self::assertSame('2026-11-10', $movie['date']);
        self::assertSame('872972', $movie['performers'][0]['external_id']);
        self::assertSame('山田ゆり', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/actress/detail/872972', $movie['performers'][0]['url']);
        self::assertSame('Madonna', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年11月10日', $movie['metadata']['search_key']);
        self::assertSame('全31作品中 1 〜 30 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('JUMS191', $result->items[29]->meta['movie']['code']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/list/date/2026-11-10?page=2', $result->pagination->nextUrl);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('madonna/actress-871344.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/871344'));
        $person = $result->meta['performer'];
        self::assertSame('871344', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/871344', $person['url']);
        self::assertSame('MATSUOKA SUZU', $person['name']);
        self::assertSame('松岡すず', $person['name_japanese']);
        self::assertSame(['松岡すず', 'MATSUOKA SUZU'], $person['aliases']);
        self::assertSame('160cm', $person['height_raw']);
        self::assertSame('B85cm (D) W58cm H89cm', $person['size_raw']);
        self::assertNull($person['birth_date_raw']);
        self::assertSame(['身長' => '160cm', '3サイズ' => 'B85cm (D) W58cm H89cm'], $person['raw_profile']);
        self::assertStringEndsWith('/actress_main/871344/SP6rD36dpodFq9ybzJa26ZriyAjc2WpEXMpv4o2V.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertCount(8, $person['metadata']['works']);
        self::assertSame('JQJ003', $person['metadata']['works'][0]['external_id']);
        self::assertSame('麗しきランジェリー、唾液と精液で汚れた酬いの人妻保険外交員', $person['metadata']['works'][0]['name']);
        self::assertStringEndsWith('/bDQxPHRbhurIDhA5pLR88nGRvS6xF07OQmkK35uk.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame(self::BASE . '/works/detail/JQJ003', $person['metadata']['works'][0]['url']);
        self::assertSame('JUR086', $person['metadata']['works'][7]['external_id']);
        self::assertSame('全8作品中 1 〜 8 タイトルを表示', $person['metadata']['work_range']);
    }
}
