<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Honnaka;

use JOOservices\CrawlerX\Adapters\Honnaka\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class HonnakaTest extends TestCase
{
    private const BASE = 'https://honnaka.jp';

    public function test_detail_fields_from_real_capture(): void
    {
        $result = (new Detail($this->clientWithFixture('honnaka/detail-HNMG003.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/HNMG003'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/HNMG003', $result->url);
        self::assertSame('HNMG003', $movie['external_id']);
        self::assertSame('HNMG003', $movie['code']);
        self::assertSame('【本中配信限定】 一杯（眠剤+媚薬入り）奢らせてくれませんか？ 美少女限定眠姦キメセク中出し', $movie['title']);
        self::assertStringStartsWith('街を歩く美少女を有名Y●ut●b●配信者だと嘘ついてナンパし', $movie['description']);
        self::assertStringEndsWith('美少女限定で眠姦キメセク中出し', $movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame(74, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/25/content/88372/k4WTOV0udkmj757WmyIeURfhKOBMwbnbo8bz0mBi.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/25/content/88372/ChiPuLgKORkjfY0kaJuBxH7hJd96N5zGkgMsj17g.jpg', $movie['screenshots'][10]['url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['ナンパ', 'フェラ', 'イタズラ', '美少女', '中出し'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('HONNAKA', $meta['maker']);
        self::assertSame('C:TAKERU', $meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => '生でグチョグチョいかせて！', 'url' => self::BASE . '/works/list/label/10099', 'external_id' => '10099']], $meta['label']);
        self::assertSame([['name' => '2026年11月24日', 'url' => self::BASE . '/works/list/date/2026-11-24', 'external_id' => '2026-11-24']], $meta['release_links']);
        self::assertSame(['5011', '113', '455', '330', '128'], array_column($meta['genres'], 'external_id'));
        self::assertSame(['ナンパ', 'フェラ', 'イタズラ', '美少女', '中出し'], array_column($meta['genres'], 'name'));
        self::assertSame(self::BASE . '/works/list/genre/5011', $meta['genres'][0]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'HNMG003', 'duration' => 74]], $meta['formats']);
        self::assertStringEndsWith('/hnmg00003dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/sample_video_image_file/HBi5G6J6iO7EXRbluMsA90SvGTlK8RQVGQhVTQfX.jpg', $meta['sample_video_poster_url']);
        self::assertCount(1, $meta['purchase_links']);
        self::assertStringContainsString('cid=hnmg00003', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(
            ['女優', '発売日', 'シリーズ', 'レーベル', 'ジャンル', '監督', '品番', '収録時間', '価格'],
            array_keys($meta['source_fields']),
        );
        self::assertSame('発売日 2026年11月24日', $meta['source_fields']['発売日']);
        self::assertSame('レーベル 生でグチョグチョいかせて！', $meta['source_fields']['レーベル']);
        self::assertSame('監督 C:TAKERU', $meta['source_fields']['監督']);
        self::assertSame('収録時間 DVD74分', $meta['source_fields']['収録時間']);
        self::assertSame('価格', $meta['source_fields']['価格']);
    }

    public function test_date_index_listing_cards_and_pagination(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('honnaka/dates.html')],
            self::BASE . '/works/list/date/2026-11-24' => ['body' => $this->loadFixture('honnaka/list-2026-11-24.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(6, $result->items);
        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/HNMG003', $first->url);
        self::assertSame('HNMG003', $movie['external_id']);
        self::assertSame('HNMG003', $movie['code']);
        self::assertSame('一杯（眠剤+媚薬入り）奢らせてくれませんか？', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/25/content/88372/DRDMAObS8uUFa3Ox58bM11fZya8GgtbxA3QP7zFU.jpg', $movie['cover_url']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame('HONNAKA', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertSame(6, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-24', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-24', $movie['metadata']['release_group']['url']);
        self::assertSame('全6作品中 1 〜 6 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(['HNMG003', 'HMN924', 'HMN913', 'HMN916', 'HMN928', 'HNDB288'], array_map(static fn($item) => $item->meta['movie']['code'], $result->items));
        $second = $result->items[1]->meta['movie'];
        self::assertSame('810107', $second['performers'][0]['external_id']);
        self::assertSame('新井リマ', $second['performers'][0]['name']);
        self::assertSame(self::BASE . '/actress/detail/810107', $second['performers'][0]['url']);
        $multi = $result->items[5]->meta['movie'];
        self::assertSame(['769498', '854079'], array_column($multi['performers'], 'external_id'));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(261, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_direct_date_listing_is_terminal(): void
    {
        $result = (new Listing($this->clientWithFixture('honnaka/list-2026-11-24.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-24'));
        self::assertCount(6, $result->items);
        self::assertSame('HNMG003', $result->items[0]->meta['movie']['code']);
        self::assertSame('2026-11-24', $result->items[0]->meta['movie']['date']);
        self::assertSame('全6作品中 1 〜 6 タイトルを表示', $result->items[0]->meta['movie']['metadata']['listing_range']);
        self::assertNull($result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('honnaka/actress-769498.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/769498'));
        $person = $result->meta['performer'];
        self::assertSame('769498', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/769498', $person['url']);
        self::assertSame('NANASE ALICE', $person['name']);
        self::assertSame('七瀬アリス', $person['name_japanese']);
        self::assertSame(['七瀬アリス', 'NANASE ALICE'], $person['aliases']);
        self::assertNull($person['height_raw']);
        self::assertNull($person['size_raw']);
        self::assertNull($person['birth_date_raw']);
        self::assertSame([], $person['raw_profile']);
        self::assertStringEndsWith('/actress_main/769498/v8mS7T1106SYqMq9ssraEv6Q7zC7l0aY0Wc0QQ6q.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertCount(12, $person['metadata']['works']);
        self::assertSame('HNDB288', $person['metadata']['works'][0]['external_id']);
        self::assertSame('七瀬アリス　本中３rd BEST 艶麗', $person['metadata']['works'][0]['name']);
        self::assertStringEndsWith('/B2p3YKe9nausv1ynmRBqYrEieh6UGMJwrem1KWlr.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertStringContainsString('/works/detail/HNDB288', $person['metadata']['works'][0]['url']);
        self::assertSame('全29作品中 1 〜 12 タイトルを表示', $person['metadata']['work_range']);
    }
}
