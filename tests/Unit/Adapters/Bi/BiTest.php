<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Bi;

use JOOservices\CrawlerX\Adapters\Bi\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class BiTest extends TestCase
{
    private const BASE = 'https://bi-av.com';

    private const CDN = 'https://cdn.up-timely.com/image/7/content/88306/';

    public function test_detail_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('bi/detail-CJOD549.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/CJOD549'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/CJOD549', $result->url);
        self::assertSame('CJOD549', $movie['external_id']);
        self::assertSame('CJOD549', $movie['code']);
        self::assertSame('帰省先のド田舎で、幼馴染のアリスと5年振りの再会。美顔でねっちょり濃厚ベロキス中出しで搾り取られ続けたボク。', $movie['title']);
        self::assertStringStartsWith('小さい頃からの憧れのお姉さんと濃厚なベロキス…。', $movie['description']);
        self::assertStringEndsWith('あの頃よりもさらに美しい…。おススメです。', $movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame(self::CDN . '1YCTZoYqFllpwOD43HEbk3SmcgvBls5aNkCk8Bhl.jpg', $movie['cover_url']);
        self::assertCount(16, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . 'UggqUx6IcTZamjRZ2VzfbZ9HzqMBDQrNW8p8Ai9O.jpg', $movie['screenshots'][15]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('870975', $movie['performers'][0]['external_id']);
        self::assertSame('釈アリス', $movie['performers'][0]['name']);
        self::assertSame('釈アリス', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/870975', $movie['performers'][0]['url']);
        self::assertSame(['キス・接吻', 'フェラ', 'お姉さん', '騎乗位', '中出し'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('BI', $meta['maker']);
        self::assertSame('豆沢豆太郎', $meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => '痴女ヘブン', 'url' => self::BASE . '/works/list/label/9617', 'external_id' => '9617']], $meta['label']);
        self::assertSame([['name' => '2026年11月24日', 'url' => self::BASE . '/works/list/date/2026-11-24', 'external_id' => '2026-11-24']], $meta['release_links']);
        self::assertSame(['9163', '113', '376', '431', '128'], array_column($meta['genres'], 'external_id'));
        self::assertSame(self::BASE . '/works/list/genre/9163', $meta['genres'][0]['url']);
        self::assertSame('キス・接吻', $meta['genres'][0]['name']);
        self::assertSame([['format' => 'DVD', 'code' => 'CJOD549', 'duration' => 120, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertStringEndsWith('/cjod00549dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/GNeJc6Qf7wsWdOirurwuvYUMRJ88PG7xvYIPch5w.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertStringContainsString('cid=cjod00549', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertStringContainsString('cid=cjod549', $meta['purchase_links'][1]['url']);
        self::assertStringContainsString('DVDを購入', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertCount(9, $meta['source_fields']);
        self::assertSame(
            ['女優', '発売日', 'シリーズ', 'レーベル', 'ジャンル', '監督', '品番', '収録時間', '価格'],
            array_keys($meta['source_fields']),
        );
        self::assertSame('レーベル 痴女ヘブン', $meta['source_fields']['レーベル']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('bi/dates.html')],
            self::BASE . '/works/list/date/2026-11-24' => ['body' => $this->loadFixture('bi/list-2026-11-24.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(7, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/CJOD549', $first->url);
        self::assertSame('CJOD549', $movie['external_id']);
        self::assertSame('CJOD549', $movie['code']);
        self::assertSame('帰省先のド田舎で、幼馴染のアリスと5年振りの再会。美顔でねっちょり濃厚ベロキス中出しで搾り取られ続けたボク。', $movie['title']);
        self::assertSame(self::CDN . 'UD6hVKaVq9EO1CU0S5azBaenzuwYPtHIiXzGujJM.jpg', $movie['cover_url']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame('870975', $movie['performers'][0]['external_id']);
        self::assertSame('釈アリス', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/actress/detail/870975', $movie['performers'][0]['url']);
        self::assertSame('BI', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame('2026-11-24', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-24', $movie['metadata']['release_group']['url']);
        self::assertSame('全7作品中 1 〜 7 タイトルを表示', $movie['metadata']['listing_range']);
        $ensemble = $result->items[6]->meta['movie'];
        self::assertSame('CJOB222', $ensemble['code']);
        self::assertSame(7, $ensemble['metadata']['performer_count']);
        self::assertSame([], $ensemble['performers']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('bi/list-2026-11-24.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-24'));
        self::assertCount(7, $result->items);
        self::assertSame('CJOD549', $result->items[0]->meta['movie']['code']);
        self::assertSame('2026-11-24', $result->items[0]->meta['movie']['date']);
        self::assertSame('発売日検索結果：2026年11月24日', $result->items[0]->meta['movie']['metadata']['search_key']);
        self::assertNull($result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('bi/actress-138332.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/138332'));
        $person = $result->meta['performer'];
        self::assertSame('138332', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/138332', $person['url']);
        self::assertSame('HATANO YUI', $person['name']);
        self::assertSame('波多野結衣', $person['name_japanese']);
        self::assertSame(['波多野結衣', 'HATANO YUI'], $person['aliases']);
        self::assertSame('1988-05-24', $person['birth_date_raw']);
        self::assertSame('163cm', $person['height_raw']);
        self::assertSame('B88cm (D) W59cm H85cm', $person['size_raw']);
        self::assertSame(['誕生日' => '1988年5月24日', '身長' => '163cm', '3サイズ' => 'B88cm (D) W59cm H85cm', '趣味' => 'ゲーム'], $person['raw_profile']);
        self::assertStringEndsWith('/actress_main/138332/YAIZb9ZhvhE58fTUQHsaF2KscDilBh6TRve2KeLo.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertCount(12, $person['metadata']['works']);
        self::assertSame('CJOB221', $person['metadata']['works'][0]['external_id']);
        self::assertStringEndsWith('/3e2aMCXdVknf4wGiUmhqcHAVY65Bh8RAddJmReUt.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame(self::BASE . '/works/detail/CJOB221', $person['metadata']['works'][0]['url']);
        self::assertSame('CJVR046', $person['metadata']['works'][11]['external_id']);
        self::assertSame('全118作品中 1 〜 12 タイトルを表示', $person['metadata']['work_range']);
    }
}
