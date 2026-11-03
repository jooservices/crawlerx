<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Moodyz;

use JOOservices\CrawlerX\Adapters\Moodyz\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class MoodyzTest extends TestCase
{
    private const BASE = 'https://moodyz.com';

    public function test_detail_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('moodyz/detail-MIDA834.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/MIDA834'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MIDA834', $result->url);
        self::assertSame('MIDA834', $movie['external_id']);
        self::assertSame('MIDA834', $movie['code']);
        self::assertSame('新人 純淫ハタチ 彼氏もいるけど刺激を求めるエロ好奇心むんむんドパガキ美少女AVデビュー！', $movie['title']);
        self::assertStringStartsWith('【ドパガキ史上もっとも美少女AVデビュー！】現役女子大生・川広えなちゃん', $movie['description']);
        self::assertStringEndsWith('次はどんな経験でドーパミン出そうかな！', $movie['description']);
        self::assertSame('2026-11-03', $movie['date']);
        self::assertSame(150, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/30/content/87967/4fPDKrKfN9yV09FtIFkRAizoNbr0iqhoeMS0wt0N.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/30/content/87967/2jCrty0DLVdZLVVizwZ0BXb7aAkQZ7BGxBKhr1Dy.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('875795', $movie['performers'][0]['external_id']);
        self::assertSame('川広えな', $movie['performers'][0]['name']);
        self::assertSame('川広えな', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/875795', $movie['performers'][0]['url']);
        self::assertSame(['美少女', '顔射', 'フェラ', 'ハメ撮り', 'デビュー作品'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('MOODYZ', $meta['maker']);
        self::assertSame('うさぴょん。', $meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => 'MOODYZ　DIVA', 'url' => self::BASE . '/works/list/label/5048', 'external_id' => '5048']], $meta['labels']);
        self::assertSame([['name' => '2026年11月3日', 'url' => self::BASE . '/works/list/date/2026-11-03', 'external_id' => '2026-11-03']], $meta['release_links']);
        self::assertSame(['330', '329', '113', '118', '315'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/330', $meta['genres'][0]['url']);
        self::assertSame('美少女', $meta['genres'][0]['name']);
        self::assertSame([['format' => 'DVD', 'code' => 'MIDA834', 'duration' => 150, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/mida00834dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/PH0MtDkNL9OvohBhHhTEu3ZxkJDtrx8e2gYzmSgv.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=mida00834', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=mida834', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertCount(9, $meta['source_fields']);
        self::assertSame('レーベル MOODYZ　DIVA', $meta['source_fields']['レーベル']);
        foreach ($movie['screenshots'] as $image) {
            self::assertStringNotContainsString('MIDA854', $image['url']);
        }
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('moodyz/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('moodyz/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(22, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MIDA854', $first->url);
        self::assertSame('MIDA854', $movie['external_id']);
        self::assertSame('MIDA854', $movie['code']);
        self::assertSame('まだ少女。だけど、えっち。幼顔×幼ボディ 至高の幼原石 AV DEBUT', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/30/content/88472/peTVqdqzBlYMAiKiUejsrOe1FYxX6S5EtAtQTAW3.jpg', $movie['cover_url']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame('873528', $movie['performers'][0]['external_id']);
        self::assertSame('齋藤めい', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/actress/detail/873528', $movie['performers'][0]['url']);
        self::assertSame('MOODYZ', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame(22, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-12-01', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $movie['metadata']['release_group']['url']);
        self::assertSame('全22作品中 1 〜 22 タイトルを表示', $movie['metadata']['listing_range']);
        $ensemble = $result->items[21]->meta['movie'];
        self::assertSame('MIZD570', $ensemble['code']);
        self::assertSame(37, $ensemble['metadata']['performer_count']);
        self::assertSame([], $ensemble['performers']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(412, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('moodyz/list-2026-11-03.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-03'));
        self::assertCount(26, $result->items);
        self::assertSame('MDVR459', $result->items[0]->meta['movie']['code']);
        self::assertSame('2026-11-03', $result->items[0]->meta['movie']['date']);
        self::assertSame('全26作品中 1 〜 26 タイトルを表示', $result->items[0]->meta['movie']['metadata']['listing_range']);
        self::assertNull($result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('moodyz/actress-713490.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/713490'));
        $person = $result->meta['performer'];
        self::assertSame('713490', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/713490', $person['url']);
        self::assertSame('ONO RIKKA', $person['name']);
        self::assertSame('小野六花', $person['name_japanese']);
        self::assertSame(['小野六花', 'ONO RIKKA'], $person['aliases']);
        self::assertSame('148cm', $person['height_raw']);
        self::assertSame('B81cm (C) W58cm H82cm', $person['size_raw']);
        self::assertNull($person['birth_date_raw']);
        self::assertSame(['身長' => '148cm', '3サイズ' => 'B81cm (C) W58cm H82cm'], $person['raw_profile']);
        self::assertStringEndsWith('/actress_main/713490/r5VAiq4mBBXGVIDaOyzlUH80H4NOvE4KdP2iP1Ov.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertCount(12, $person['metadata']['works']);
        self::assertSame('MIDA876', $person['metadata']['works'][0]['external_id']);
        self::assertSame('年下女子のおっぱいに甘やかされたい中出しエッチ', $person['metadata']['works'][0]['name']);
        self::assertStringEndsWith('/3ZaPKxswMR3Rbe2PExC1rwn9QYbdGOrNFS3PRmja.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertStringContainsString('/works/detail/MIDA876', $person['metadata']['works'][0]['url']);
        self::assertSame('MIDA766', $person['metadata']['works'][11]['external_id']);
        self::assertSame('全186作品中 1 〜 12 タイトルを表示', $person['metadata']['work_range']);
    }
}
