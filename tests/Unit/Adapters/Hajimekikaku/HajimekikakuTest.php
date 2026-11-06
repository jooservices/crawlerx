<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Hajimekikaku;

use JOOservices\CrawlerX\Adapters\Hajimekikaku\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class HajimekikakuTest extends TestCase
{
    private const BASE = 'https://hajimekikaku.com';

    public function test_detail_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('hajimekikaku/detail-HJMO745.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/HJMO745'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/HJMO745', $result->url);
        self::assertSame('HJMO745', $movie['external_id']);
        self::assertSame('HJMO745', $movie['code']);
        self::assertStringStartsWith('制限時間内に〇発口内発射受け止めフェラ抜きチャレンジ！', $movie['title']);
        self::assertStringEndsWith('失敗したら即ズボ中出し罰ゲーム！', $movie['title']);
        self::assertStringStartsWith('今回も割と可愛い受付嬢に声をかけてジュポフェラHOUSEにご案内！', $movie['description']);
        self::assertStringEndsWith('咥え好きオフィスワーカー女子の羞恥ミッション！', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(160, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/28/content/88236/TkGVJmwZW3W7CQ73ykRsF3Qu7qBd1IcQmefMjXAn.jpg', $movie['cover_url']);
        self::assertCount(17, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/28/content/88236/FKNBpHgmhSYPisVkWn9t8dDqnYyjjlxQVcZ97GPR.jpg', $movie['screenshots'][11]['url']);
        self::assertSame('https://cdn.up-timely.com/image/28/content/88236/rXmDBnIB8FZgzsrwSR2I44UmGnKPiIxMlugPMqZH.jpg', $movie['screenshots'][16]['url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['即ハメ', '受付嬢', '羞恥', '中出し', 'フェラ'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('はじめ企画', $meta['maker']);
        self::assertSame('ビバ☆ゴンゾ', $meta['director']);
        self::assertSame([['name' => '出された精子こぼしちゃダメよ', 'url' => self::BASE . '/works/list/series/6161', 'external_id' => '6161']], $meta['series']);
        self::assertSame([['name' => '2026年11月17日', 'url' => self::BASE . '/works/list/date/2026-11-17', 'external_id' => '2026-11-17']], $meta['release_links']);
        self::assertSame(['451', '468', '333', '128', '113'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/451', $meta['genres'][0]['url']);
        self::assertSame('即ハメ', $meta['genres'][0]['name']);
        self::assertSame([['format' => 'DVD', 'code' => 'HJMO745', 'duration' => 160, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/hjmo00745dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/uPa6s8ijvw9ewFG6FzUiVPtx5MiJvRYKrZrD7HOI.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=hjmo00745', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=hjmo745', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('発売日 2026年11月17日', $meta['source_fields']['発売日']);
        self::assertSame('監督 ビバ☆ゴンゾ', $meta['source_fields']['監督']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        foreach ($movie['screenshots'] as $image) {
            self::assertStringContainsString('/content/88236/', $image['url']);
        }
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('hajimekikaku/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('hajimekikaku/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(5, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/HJMO745', $first->url);
        self::assertSame('HJMO745', $movie['external_id']);
        self::assertSame('HJMO745', $movie['code']);
        self::assertSame('「出された精子こぼしちゃダメよ」お口から精子垂れ流しじゅぽじゅぽノーハンドフェラ大会！6', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/28/content/88236/fx9SWmb6vUfD0TAlcz3nlvrnt1gd9MYLmfgZ2zU0.jpg', $movie['cover_url']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame('はじめ企画', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame('発売日検索結果：2026年11月17日', $movie['metadata']['search_key']);
        self::assertSame('全5作品中 1 〜 5 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(['HJMO745', 'HJMO740', 'HJMO747', 'HJBB248', 'HJBB249'], array_map(static fn($item) => $item->meta['movie']['code'], $result->items));
        self::assertGreaterThanOrEqual(1, $result->pagination->currentPage);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('hajimekikaku/list-2026-11-17.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-17'));
        self::assertCount(5, $result->items);
        self::assertSame('2026-11-17', $result->items[4]->meta['movie']['date']);
        self::assertNull($result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
        self::assertFalse($result->pagination->hasNextPage);
    }
}
