<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Ebody;

use JOOservices\CrawlerX\Adapters\Ebody\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class EbodyTest extends TestCase
{
    private const BASE = 'https://av-e-body.com';

    public function test_detail_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('ebody/detail-EBWH392.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/EBWH392'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/EBWH392', $result->url);
        self::assertSame('EBWH392', $movie['external_id']);
        self::assertSame('EBWH392', $movie['code']);
        self::assertSame('ヌケるJ乳 新人メイクアップアーティスト(22) B100cm W57cm H83cm お試しデビュー', $movie['title']);
        self::assertStringStartsWith('新人メイクアップアーティスト（22）夏目琴理がE-BODYからお試しデビュー。', $movie['description']);
        self::assertStringEndsWith('あなたは彼女の完璧プロポーションに溺れる。', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(160, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/6/content/88147/rITA4HQvEvZn78UoXr91su0zDpmZG3d25x3YN3v6.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/6/content/88147/4ha56LYoNBCQthoIiG4QvzqjVAWP75ezuX2RKOlk.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('873771', $movie['performers'][0]['external_id']);
        self::assertSame('夏目琴理', $movie['performers'][0]['name']);
        self::assertSame('夏目琴理', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/873771', $movie['performers'][0]['url']);
        self::assertSame(['巨乳', 'パイズリ', 'フェラ', 'アクメ・オーガズム', 'デビュー作品'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('E-BODY', $meta['maker']);
        self::assertSame('TAKE-D', $meta['director']);
        self::assertSame([['name' => 'E-BODY専属デビュー', 'url' => self::BASE . '/works/list/series/5256', 'external_id' => '5256']], $meta['series']);
        self::assertSame([['name' => 'E-BODY', 'url' => self::BASE . '/works/list/label/7411', 'external_id' => '7411']], $meta['label']);
        self::assertSame([['name' => '2026年11月17日', 'url' => self::BASE . '/works/list/date/2026-11-17', 'external_id' => '2026-11-17']], $meta['release_links']);
        self::assertSame(['215', '126', '113', '9155', '315'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/215', $meta['genres'][0]['url']);
        self::assertSame('巨乳', $meta['genres'][0]['name']);
        self::assertSame([
            ['format' => 'DVD', 'code' => 'EBWH392', 'duration' => 160, 'price' => '4,048円（税込）'],
            ['format' => 'Blu-ray', 'code' => '9EBWH392', 'duration' => 160, 'price' => '5,148円（税込）'],
        ], $meta['formats']);
        self::assertStringEndsWith('/ebwh00392dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/vL8uiXgm7rUy18At0lWK9KlJL8nZ3WD27pbCRr63.jpg', $meta['sample_video_poster_url']);
        self::assertCount(3, $meta['purchase_links']);
        self::assertStringContainsString('cid=ebwh00392', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=ebwh392', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertStringContainsString('cid=9ebwh392', $meta['purchase_links'][2]['url']);
        self::assertStringContainsString('Blu-rayを購入', $meta['purchase_links'][2]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertCount(9, $meta['source_fields']);
        self::assertSame('レーベル E-BODY', $meta['source_fields']['レーベル']);
        self::assertSame('監督 TAKE-D', $meta['source_fields']['監督']);
        self::assertSame(
            ['女優', '発売日', 'シリーズ', 'レーベル', 'ジャンル', '監督', '品番', '収録時間', '価格'],
            array_keys($meta['source_fields']),
        );
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('ebody/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('ebody/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(11, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/EBWH392', $first->url);
        self::assertSame('EBWH392', $movie['external_id']);
        self::assertSame('EBWH392', $movie['code']);
        self::assertSame('ヌケるJ乳 新人メイクアップアーティスト(22) B100cm W57cm H83cm お試しデビュー', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/6/content/88147/v6S8gDdWdoXXJV4CNrqOUzVCzS8pRYWx0jKVDSYW.jpg', $movie['cover_url']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame('873771', $movie['performers'][0]['external_id']);
        self::assertSame('夏目琴理', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/actress/detail/873771', $movie['performers'][0]['url']);
        self::assertSame('E-BODY', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame(11, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-17', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-17', $movie['metadata']['release_group']['url']);
        self::assertSame('全11作品中 1 〜 11 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(173, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('ebody/list-2026-11-17.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-17'));
        self::assertCount(11, $result->items);
        self::assertSame('EBWH392', $result->items[0]->meta['movie']['code']);
        self::assertSame('2026-11-17', $result->items[0]->meta['movie']['date']);
        self::assertSame('発売日検索結果：2026年11月17日', $result->items[0]->meta['movie']['metadata']['search_key']);
        self::assertSame('全11作品中 1 〜 11 タイトルを表示', $result->items[0]->meta['movie']['metadata']['listing_range']);
        self::assertNull($result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('ebody/actress-873771.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/873771'));
        $person = $result->meta['performer'];
        self::assertSame('873771', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/873771', $person['url']);
        self::assertSame('NATSUME　KOTORI', $person['name']);
        self::assertSame('夏目琴理', $person['name_japanese']);
        self::assertSame(['夏目琴理', 'NATSUME　KOTORI'], $person['aliases']);
        self::assertSame('2004-04-22', $person['birth_date_raw']);
        self::assertSame('155cm', $person['height_raw']);
        self::assertSame('B100cm (J) W57cm H83cm', $person['size_raw']);
        self::assertSame([
            '誕生日' => '2004年4月22日',
            '身長' => '155cm',
            '3サイズ' => 'B100cm (J) W57cm H83cm',
            '出身地' => '兵庫県',
            '血液型' => 'A型',
        ], $person['raw_profile']);
        self::assertStringEndsWith('/actress_main/873771/oPY5h4zU05LjziIBpjiJxjDZb9GI6XRjbcrP3Zi1.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertCount(1, $person['metadata']['works']);
        self::assertSame('EBWH392', $person['metadata']['works'][0]['external_id']);
        self::assertSame('ヌケるJ乳 新人メイクアップアーティスト(22) B100cm W57cm H83cm お試しデビュー', $person['metadata']['works'][0]['name']);
        self::assertStringEndsWith('/content/88147/v6S8gDdWdoXXJV4CNrqOUzVCzS8pRYWx0jKVDSYW.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame(self::BASE . '/works/detail/EBWH392', $person['metadata']['works'][0]['url']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }
}
