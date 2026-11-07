<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Jav321;

use JOOservices\CrawlerX\Adapters\Jav321\Types\Detail;
use JOOservices\CrawlerX\Adapters\Jav321\Types\Listing;
use JOOservices\CrawlerX\Adapters\Jav321\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/jav321/';

    public function test_detail_returns_every_field_it_offers(): void
    {
        $movie = $this->detail('detail-118rtp00020.html');

        self::assertSame('118rtp00020', $movie['external_id']);
        self::assertSame('rtp-020', $movie['code']);
        self::assertStringStartsWith('再婚相手の連れ子は美人女子校生姉妹！！', $movie['title']);
        self::assertStringEndsWith('興奮し身体をくねらせていたので……', $movie['title']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/118rtp00020/118rtp00020pl.jpg', $movie['cover_url']);
        self::assertSame('2014-03-01', $movie['date']);
        self::assertSame(135, $movie['duration']);
        self::assertSame(['ドキュメンタリー', 'ハイビジョン', '女子校生', '妄想', '姉・妹'], $movie['tags']);
        self::assertStringStartsWith('再婚相手のかわいい娘たち。', $movie['description']);
        self::assertSame([], $movie['performers']);

        $meta = $movie['metadata'];
        self::assertSame('プレステージ', $meta['maker']);
        self::assertSame('https://www.jav321.com/company/%E3%83%97%E3%83%AC%E3%82%B9%E3%83%86%E3%83%BC%E3%82%B8/1', $meta['maker_url']);
        self::assertSame('再婚相手の連れ子は美人女子校生姉妹！！', $meta['series']);
        self::assertSame('https://www.jav321.com/series/212067/1', $meta['series_url']);
        self::assertSame(10828, $meta['favorite_count']);
        self::assertSame(['score' => 4.0, 'scale' => 5.0, 'votes' => null], $meta['rating']);
        self::assertSame('http://cc3001.r18.com/litevideo/freepv/1/118/118rtp020/118rtp020_dmb_w.mp4', $meta['sample_video_url']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/118rtp00020/118rtp00020ps.jpg', $meta['cover_thumbnail_url']);
        self::assertArrayNotHasKey('related', $meta);

        self::assertSame([
            ['name' => 'ドキュメンタリー', 'id' => '4023', 'url' => 'https://www.jav321.com/genre/4023/1'],
            ['name' => 'ハイビジョン', 'id' => '6533', 'url' => 'https://www.jav321.com/genre/6533/1'],
            ['name' => '女子校生', 'id' => '1018', 'url' => 'https://www.jav321.com/genre/1018/1'],
            ['name' => '妄想', 'id' => '4028', 'url' => 'https://www.jav321.com/genre/4028/1'],
            ['name' => '姉・妹', 'id' => '4057', 'url' => 'https://www.jav321.com/genre/4057/1'],
        ], $meta['genres']);

        self::assertSame([
            'メーカー' => 'プレステージ',
            'ジャンル' => 'ドキュメンタリー ハイビジョン 女子校生 妄想 姉・妹',
            '品番' => 'rtp-020',
            '配信開始日' => '2014-03-01',
            '収録時間' => '135 minutes',
            'お気に入り登録数' => '10828',
            '平均評価' => '4',
            'シリーズ' => '再婚相手の連れ子は美人女子校生姉妹！！',
        ], $meta['source_fields']);

        self::assertCount(10, $movie['screenshots']);
        foreach ($movie['screenshots'] as $index => $shot) {
            self::assertSame(sprintf('http://pics.dmm.co.jp/digital/video/118rtp00020/118rtp00020jp-%d.jpg', $index + 1), $shot['url']);
            self::assertNull($shot['thumbnail_url']);
            self::assertSame($index, $shot['metadata']['position']);
        }
    }

    public function test_detail_with_performers_returns_cast_and_photos(): void
    {
        $movie = $this->detail('detail-ssis00001.html', 'https://www.jav321.com/video/ssis00001');

        self::assertSame('ssis00001', $movie['external_id']);
        self::assertSame('ssis-001', $movie['code']);
        self::assertSame('一ヶ月間の禁欲の果てに彼女のルームメイト2人と浮気SEXだけに没頭した彼女不在の3日間。 葵つかさ 乙白さやか', $movie['title']);
        self::assertSame('2021-02-19', $movie['date']);
        self::assertSame(147, $movie['duration']);
        self::assertStringStartsWith('S1スリム美女優の豪華共演エモドラマ作！', $movie['description']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/ssis00001/ssis00001pl.jpg', $movie['cover_url']);
        self::assertSame([], $movie['tags']);

        self::assertCount(2, $movie['performers']);
        self::assertSame('1006229', $movie['performers'][0]['external_id']);
        self::assertSame('葵つかさ', $movie['performers'][0]['name']);
        self::assertSame('https://www.jav321.com/star/1006229/1', $movie['performers'][0]['url']);
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/aoi_tukasa.jpg', $movie['performers'][0]['profile_image_url']);
        self::assertSame('1060823', $movie['performers'][1]['external_id']);
        self::assertSame('乙白さやか', $movie['performers'][1]['name']);
        self::assertSame('https://www.jav321.com/star/1060823/1', $movie['performers'][1]['url']);
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/otosiro_sayaka.jpg', $movie['performers'][1]['profile_image_url']);

        $meta = $movie['metadata'];
        self::assertSame('エスワン ナンバーワンスタイル', $meta['maker']);
        self::assertSame(3634, $meta['favorite_count']);
        self::assertSame(['score' => 4.5, 'scale' => 5.0, 'votes' => null], $meta['rating']);
        self::assertSame('https://awscc3001.r18.com/litevideo/freepv/s/ssi/ssis00001/ssis00001_dmb_w.mp4', $meta['sample_video_url']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/ssis00001/ssis00001ps.jpg', $meta['cover_thumbnail_url']);
        self::assertSame('葵つかさ 乙白さやか', $meta['source_fields']['出演者']);

        self::assertCount(10, $movie['screenshots']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/ssis00001/ssis00001jp-10.jpg', $movie['screenshots'][9]['url']);
        self::assertSame(9, $movie['screenshots'][9]['metadata']['position']);
    }

    public function test_english_detail_uses_english_labels(): void
    {
        $movie = $this->detail('detail-en-118rtp00020.html', 'https://en.jav321.com/video/118rtp00020');

        self::assertSame('rtp-020', $movie['code']);
        self::assertSame('2014-03-01', $movie['date']);
        self::assertSame(135, $movie['duration']);
        self::assertSame(10828, $movie['metadata']['favorite_count']);
        self::assertSame('プレステージ', $movie['metadata']['maker']);
        self::assertSame(['ドキュメンタリー', 'ハイビジョン', '女子校生', '妄想', '姉・妹'], $movie['tags']);
        self::assertSame('再婚相手の連れ子は美人女子校生姉妹！！', $movie['metadata']['series']);
    }

    public function test_listing_cards_carry_url_code_title_cover_and_download_flag(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing-genre-1018.html'))))
            ->execute(new CrawlRequestDto(url: 'https://www.jav321.com/genre/1018/1', type: CrawlType::Listing));

        self::assertCount(48, $result->items);
        self::assertSame('https://www.jav321.com/genre/1018/2', $result->pagination->nextUrl);

        $first = $result->items[0]->meta['movie'];
        self::assertSame('https://www.jav321.com/video/hunta00805', $result->items[0]->url);
        self::assertSame('hunta00805', $first['external_id']);
        self::assertSame('hunta-805', $first['code']);
        self::assertStringStartsWith('だれとでも定額挿れ放題！', $first['title']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/hunta00805/hunta00805ps.jpg', $first['cover_url']);
        self::assertFalse($first['metadata']['has_download']);

        $withDownload = null;
        foreach ($result->items as $item) {
            if (($item->meta['movie']['metadata']['has_download'] ?? false) === true) {
                $withDownload = $item->meta['movie'];
                break;
            }
        }

        self::assertNotNull($withDownload);
        self::assertSame('snis-487', $withDownload['code']);
    }

    public function test_new_release_listing_parses_cards(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing-release-date-1.html'))))
            ->execute(new CrawlRequestDto(url: 'https://www.jav321.com/list/release_date/1', type: CrawlType::Listing));

        self::assertNotEmpty($result->items);
        self::assertSame('https://www.jav321.com/list/release_date/2', $result->pagination->nextUrl);

        $first = $result->items[0]->meta['movie'];
        self::assertSame('748spay-856', $first['external_id']);
        self::assertSame('spay-856', $first['code']);
        self::assertSame('ひかる', $first['title']);
        self::assertSame('https://www.jav321.com/images/748spay/856/pf_o1_748spay-856.jpg', $first['cover_url']);
    }

    public function test_performer_listing_carries_name_and_image(): void
    {
        $result = (new PerformerListing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'performer-listing-stars-1.html'))))
            ->execute(new CrawlRequestDto(url: 'https://www.jav321.com/stars/1', type: CrawlType::PerformerListing));

        self::assertNotEmpty($result->items);
        self::assertSame('https://www.jav321.com/stars/2', $result->pagination->nextUrl);

        $first = $result->items[0]->meta['performer'];
        self::assertSame('1024002', $first['external_id']);
        self::assertSame('桃谷エリカ', $first['name']);
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/momotani_erika.jpg', $first['profile_image_url']);
    }

    public function test_performer_works_page_is_a_movie_listing(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing-star-1012844.html'))))
            ->execute(new CrawlRequestDto(url: 'https://www.jav321.com/star/1012844/1', type: CrawlType::Listing));

        self::assertSame('https://www.jav321.com/star/1012844/2', $result->pagination->nextUrl);
        self::assertSame('https://www.jav321.com/video/1start00658', $result->items[0]->url);
        self::assertSame('start-658', $result->items[0]->meta['movie']['code']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/1start00658/1start00658ps.jpg', $result->items[0]->meta['movie']['cover_url']);
    }

    /** @return array<string, mixed> */
    private function detail(string $fixture, string $url = 'https://www.jav321.com/video/118rtp00020'): array
    {
        return (new Detail($this->clientWithHtml((string) file_get_contents(self::FIXTURES . $fixture))))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail))
            ->meta['movie'];
    }
}
