<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Hhh;

use JOOservices\CrawlerX\Adapters\Hhh\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class HhhTest extends TestCase
{
    private const BASE = 'https://hhh-av.com';

    private const CDN = 'https://cdn.up-timely.com/image/29/content/';

    public function test_detail_without_actress_exposes_every_labelled_row(): void
    {
        $result = (new Detail($this->clientWithFixture('hhh/detail-HUNTC688.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/HUNTC688'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/HUNTC688', $result->url);
        self::assertSame('HUNTC688', $movie['external_id']);
        self::assertSame('HUNTC688', $movie['code']);
        self::assertSame('時間を止めれる！時間を戻せちゃう！！ボクだけが時間を自由自在に操れるようになった世界線！！', $movie['title']);
        self::assertStringStartsWith('【新感覚時間停止AV】時間を止めれる！時間を戻せる！', $movie['description']);
        self::assertStringEndsWith('時間師マジ最高っす！', $movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame(210, $movie['duration']);
        self::assertSame(self::CDN . '88395/tzpBODgCVriICZIN9cOq5VAe7UGE21Izw1SSzgbg.jpg', $movie['cover_url']);
        self::assertCount(16, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . '88395/6m423acGdV0gHriKh1wXhZORuKyRMTXBILWYSwA7.jpg', $movie['screenshots'][1]['url']);
        self::assertSame(self::CDN . '88395/Jgxpvl14akYvcNqZQyrfFEmLY69ruzmrKfjSqXTY.jpg', $movie['screenshots'][15]['url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['フェラ', '騎乗位', '女子校生', 'バック', '中出し'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('HHH', $meta['maker']);
        self::assertSame('ボルボ中野', $meta['director']);
        self::assertSame([['name' => '時間を操れるようになった世界線', 'url' => self::BASE . '/works/list/series/6462', 'external_id' => '6462']], $meta['series']);
        self::assertSame([['name' => 'Hunter', 'url' => self::BASE . '/works/list/label/34858', 'external_id' => '34858']], $meta['label']);
        self::assertSame([['name' => '2026年11月24日', 'url' => self::BASE . '/works/list/date/2026-11-24', 'external_id' => '2026-11-24']], $meta['release_links']);
        self::assertSame(['113', '431', '202', '9170', '128'], array_column($meta['genres'], 'external_id'));
        self::assertSame(['フェラ', '騎乗位', '女子校生', 'バック', '中出し'], array_column($meta['genres'], 'name'));
        self::assertSame(self::BASE . '/works/list/genre/113', $meta['genres'][0]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'HUNTC688', 'duration' => 210, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/huntc00688dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . '88395/sample_video_image_file/GlQ8evbqJOxuDQ78LIVPalJF00X9xeD4cHFu57HZ.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=huntc00688', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=huntc688', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertCount(9, $meta['source_fields']);
        self::assertSame('メーカー Hunter', $meta['source_fields']['メーカー']);
        self::assertSame('女優', $meta['source_fields']['女優']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        self::assertStringNotContainsString('HUNTC686', json_encode($movie, JSON_THROW_ON_ERROR));
    }

    public function test_detail_keeps_unlinked_actress_name(): void
    {
        $result = (new Detail($this->clientWithFixture('hhh/detail-ROYD377.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/ROYD377'));
        $movie = $result->meta['movie'];
        self::assertSame('ROYD377', $movie['code']);
        self::assertSame('ルームメイトはまさかの女子！童貞のボクが無防備エロチラする美女とひとつ屋根の下...', $movie['title']);
        self::assertStringStartsWith('ネットでルームメイト募集の人のところにいったら', $movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('七海那美', $movie['performers'][0]['name']);
        self::assertSame('七海那美', $movie['performers'][0]['name_japanese']);
        self::assertNull($movie['performers'][0]['url']);
        self::assertNull($movie['performers'][0]['external_id']);
        self::assertSame(['巨乳', 'フェラ', '騎乗位', 'デジモ', '中出し'], $movie['tags']);
        self::assertCount(19, $movie['screenshots']);
        self::assertSame(self::CDN . '88380/WzPD89gycS31EkZQyMkx2IsExJZjw0PVVHP0y8Rq.jpg', $movie['cover_url']);
        $meta = $movie['metadata'];
        self::assertSame('こういちダビッドソン', $meta['director']);
        self::assertSame([['name' => 'ROYAL', 'url' => self::BASE . '/works/list/label/35219', 'external_id' => '35219']], $meta['label']);
        self::assertSame('5935', $meta['series'][0]['external_id']);
        self::assertSame([['format' => 'DVD', 'code' => 'ROYD377', 'duration' => 120, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertNull($meta['sample_video_url']);
        self::assertNull($meta['sample_video_poster_url']);
        self::assertSame('女優 七海那美', $meta['source_fields']['女優']);
        self::assertCount(9, $meta['source_fields']);
    }

    public function test_date_listing_cards_without_actress_pages(): void
    {
        $result = (new Listing($this->clientWithFixture('hhh/list-2026-11-24.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-24'));
        self::assertCount(17, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/HUNTC688', $first->url);
        self::assertSame('HUNTC688', $movie['external_id']);
        self::assertSame('HUNTC688', $movie['code']);
        self::assertSame('時間を止めれる！時間を戻せちゃう！！ボクだけが時間を自由自在に操れるようになった世界線！！', $movie['title']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertStringStartsWith(self::CDN . '88395/', $movie['cover_url']);
        self::assertSame([], $movie['performers']);
        self::assertSame('HHH', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年11月24日', $movie['metadata']['search_key']);
        self::assertSame('全17作品中 1 〜 17 タイトルを表示', $movie['metadata']['listing_range']);
        $withActress = $result->items[1]->meta['movie'];
        self::assertSame('ROYD380', $withActress['code']);
        self::assertSame(['逢沢みゆ'], array_column($withActress['performers'], 'name'));
        self::assertSame('HHF120', $result->items[16]->meta['movie']['code']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_second_date_listing_cards(): void
    {
        $result = (new Listing($this->clientWithFixture('hhh/list-2026-11-10.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-10'));
        self::assertCount(20, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('SODAH012', $movie['code']);
        self::assertSame('2026-11-10', $movie['date']);
        self::assertSame(['彩月七緒'], array_column($movie['performers'], 'name'));
        self::assertStringStartsWith('【完全主観ASMR】あの夏、フェス帰りのラブホテル。', $movie['title']);
        self::assertStringEndsWith('/P4eZqq1cWtPT3GV5AefNdoOGTQquI7vgFNaM6zMI.jpg', $movie['cover_url']);
        self::assertSame('HHF117', $result->items[19]->meta['movie']['code']);
    }

    public function test_date_index_follows_first_release_group_and_paginates(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('hhh/dates.html')],
            self::BASE . '/works/list/date/2026-11-24' => ['body' => $this->loadFixture('hhh/list-2026-11-24.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(17, $result->items);
        $group = $result->items[0]->meta['movie']['metadata']['release_group'];
        self::assertSame('2026-11-24', $group['date']);
        self::assertSame(17, $group['count']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-24', $group['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }
}
