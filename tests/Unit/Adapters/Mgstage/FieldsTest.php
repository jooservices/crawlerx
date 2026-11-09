<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Mgstage;

use JOOservices\CrawlerX\Adapters\Mgstage\Types\Detail;
use JOOservices\CrawlerX\Adapters\Mgstage\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class FieldsTest extends TestCase
{
    private const LUXU = 'https://www.mgstage.com/product/product_detail/259LUXU-1000/';

    private const SIRO = 'https://www.mgstage.com/product/product_detail/SIRO-5767/';

    private const LIST = 'https://www.mgstage.com/search/cSearch.php?search_word=&sort=new&list_cnt=30&type=top';

    public function test_detail_returns_every_field_it_offers(): void
    {
        $movie = $this->detail('detail-259luxu-1000.html', self::LUXU);
        $meta = $movie['metadata'];

        self::assertSame('259LUXU-1000', $movie['external_id']);
        self::assertSame('259LUXU-1000', $movie['code']);
        self::assertSame('ラグジュTV 982', $movie['title']);
        self::assertStringStartsWith('初めまして、上山瑞樹です。', $movie['description']);
        self::assertStringEndsWith('スレンダー美人が乱れる姿はぜひ本編でお楽しみください。', $movie['description']);
        self::assertSame('2018-08-06', $movie['date']);
        self::assertSame(74, $movie['duration']);
        self::assertSame(['顔射', '美乳', '美脚', '美尻', 'お姉さん', '素人', '独占配信', 'スレンダー'], $movie['tags']);

        self::assertCount(1, $movie['performers']);
        self::assertSame('上山瑞樹 26歳 研究員', $movie['performers'][0]['name']);
        self::assertSame(
            'https://www.mgstage.com/search/cSearch.php?actor[]=%E4%B8%8A%E5%B1%B1%E7%91%9E%E6%A8%B9%2026%E6%AD%B3%20%E7%A0%94%E7%A9%B6%E5%93%A1_0&type=top',
            $movie['performers'][0]['url'],
        );

        self::assertSame('ラグジュTV', $meta['maker']);
        self::assertSame('https://www.mgstage.com/search/cSearch.php?maker[]=%E3%83%A9%E3%82%B0%E3%82%B8%E3%83%A5TV_0&type=top', $meta['maker_url']);
        self::assertSame('ラグジュTV', $meta['series']);
        self::assertSame('https://www.mgstage.com/search/cSearch.php?series[]=%E3%83%A9%E3%82%B0%E3%82%B8%E3%83%A5TV_0&type=top', $meta['series_url']);
        self::assertArrayNotHasKey('label', $meta, 'the label row is empty on this work');
        self::assertCount(8, $meta['genres']);
        self::assertSame(['name' => '顔射', 'url' => 'https://www.mgstage.com/search/cSearch.php?genre[]=%E9%A1%94%E5%B0%84&type=top'], $meta['genres'][0]);
        self::assertSame(['Windows/Mac', 'iPhone/Android'], $meta['devices']);
        self::assertSame('DVD未発売', $meta['dvd_release_note']);
        self::assertArrayNotHasKey('dvd_release_date', $meta);
        self::assertSame(['score' => 3.7, 'scale' => 5.0, 'votes' => 4], $meta['rating']);
        self::assertSame(386, $meta['favorite_count']);
        self::assertSame(39358, $meta['views']);
        self::assertSame(['dokusen'], $meta['badges']);
        self::assertSame('https://www.mgstage.com/sampleplayer/sampleplayer.html/b9644ae2-1ba0-4971-92a6-46faf14274eb', $meta['sample_player_url']);
        self::assertArrayNotHasKey('sample_video_url', $meta, 'the stream answer is not part of this capture');
        self::assertArrayNotHasKey('sale_ends_at', $meta);

        self::assertSame([
            '出演' => '上山瑞樹 26歳 研究員',
            'メーカー' => 'ラグジュTV',
            '収録時間' => '74min',
            '品番' => '259LUXU-1000',
            '配信開始日' => '2018/08/06',
            '商品発売日' => 'DVD未発売',
            'シリーズ' => 'ラグジュTV',
            'ジャンル' => '顔射 美乳 美脚 美尻 お姉さん 素人 独占配信 スレンダー',
            '対応デバイス' => 'Windows/Mac , iPhone/Android',
            '評価' => '3.7 (4 件) レビューを見る',
        ], $meta['source_fields']);
    }

    public function test_detail_prices_sale_reviews_and_sample_video(): void
    {
        $movie = $this->detail('detail-siro-5767.html', self::SIRO, true);
        $meta = $movie['metadata'];

        self::assertSame('SIRO-5767', $movie['external_id']);
        self::assertStringEndsWith('【初撮り】ネットでAV応募→AV体験撮影 2649', $movie['title']);
        self::assertSame('2026-11-09', $movie['date']);
        self::assertSame(67, $movie['duration']);
        self::assertSame('るな 26歳 ガソリンスタンド店員', $movie['performers'][0]['name']);
        self::assertNull($movie['performers'][0]['url'], 'this actor is plain text on the page');
        self::assertSame('シロウトTV', $meta['maker']);
        self::assertSame('【初撮り】ネットでAV応募→AV体験撮影', $meta['series']);
        self::assertSame(['score' => 4.5, 'scale' => 5.0, 'votes' => 4], $meta['rating']);
        self::assertSame(348, $meta['favorite_count']);
        self::assertSame(2607, $meta['views']);
        self::assertSame(['sp', 'dokusen'], $meta['badges']);
        self::assertSame('2026-11-10T10:00:00+09:00', $meta['sale_ends_at']);

        self::assertSame(
            'https://sample.mgstage.com/sample/shirouto/siro/5767/siro-5767_20260923T160002.ism/request?uid=10000000-0000-0000-0000-00000000000a&pid=56d253a6-5c48-40c5-8859-6a848f9f16f0',
            $meta['sample_video_url'],
        );

        self::assertCount(2, $meta['prices']);
        self::assertSame('download_hd', $meta['prices'][0]['format']);
        self::assertSame('HD版ダウンロード + HD版ストリーミング', $meta['prices'][0]['label']);
        self::assertSame(1141.0, $meta['prices'][0]['price']['amount']);
        self::assertSame('JPY', $meta['prices'][0]['price']['currency']);
        self::assertSame(1630.0, $meta['prices'][0]['original_price']['amount']);
        self::assertSame('無期限', $meta['prices'][0]['expires']);
        self::assertSame('streaming', $meta['prices'][1]['format']);
        self::assertSame(546.0, $meta['prices'][1]['price']['amount']);
        self::assertSame('7日間', $meta['prices'][1]['expires']);
        self::assertSame(546.0, $meta['price']['amount']);

        self::assertCount(4, $meta['reviews']);
        self::assertSame([
            'title' => 'いれた瞬間がいい',
            'date' => '2026-11-09',
            'author' => 'Junk',
            'stars' => 4.0,
            'text' => 'こんなに感じてくれるなんて…見てるだけでも興奮します！演技じゃない反応がぞくぞくする！！',
        ], $meta['reviews'][1]);
        self::assertSame(5.0, $meta['reviews'][0]['stars']);
    }

    public function test_reviews_listed_twice_by_the_accordion_are_kept_once(): void
    {
        $movie = $this->detail('detail-259luxu-1000.html', self::LUXU);

        self::assertCount(4, $movie['metadata']['reviews']);
        self::assertSame(
            ['可もなく不可もなく', '一見地味かと思いきや…！？', '顔とスタイル、オナニーの仕方、すべてがエロいです！', 'お尻すき'],
            array_column($movie['metadata']['reviews'], 'title'),
        );
        self::assertSame(3.0, $movie['metadata']['reviews'][0]['stars']);
        self::assertSame('2018-08-07', $movie['metadata']['reviews'][0]['date']);
    }

    public function test_director_and_dvd_release_date_rows_are_mapped(): void
    {
        $html = $this->loadFixture('mgstage/detail-259luxu-1000.html');
        $html = str_replace('<th>商品発売日：</th>', '<th>商品発売日：</th><td>2019/01/05</td></tr><tr><th>監督：</th><td>山田 太郎</td></tr><tr><th>x</th>', $html);

        $movie = (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::LUXU, type: CrawlType::Detail))
            ->meta['movie'];

        self::assertSame('山田 太郎', $movie['metadata']['director']);
        self::assertSame('2019-01-05', $movie['metadata']['dvd_release_date']);
        self::assertArrayNotHasKey('dvd_release_note', $movie['metadata']);
        self::assertSame('山田 太郎', $movie['metadata']['source_fields']['監督']);
    }

    public function test_an_unreadable_sample_answer_leaves_the_sample_video_out(): void
    {
        $html = $this->loadFixture('mgstage/detail-siro-5767.html');
        $bad = ['https://www.mgstage.com/sampleplayer/sampleRespons.php?pid=56d253a6-5c48-40c5-8859-6a848f9f16f0' => ['body' => '{"url":""}']];

        foreach ([[], $bad] as $extra) {
            $client = $this->clientWithMappedResponses([self::SIRO => ['body' => $html, 'headers' => ['Content-Type' => 'text/html']]] + $extra);
            $movie = (new Detail($client))->execute(new CrawlRequestDto(url: self::SIRO, type: CrawlType::Detail))->meta['movie'];

            self::assertArrayNotHasKey('sample_video_url', $movie['metadata']);
            self::assertNotEmpty($movie['metadata']['sample_player_url']);
        }
    }

    public function test_a_page_without_a_product_is_rejected(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Detail($this->clientWithHtml('<html><body><article id="center_column"></article></body></html>')))
            ->execute(new CrawlRequestDto(url: self::LUXU, type: CrawlType::Detail));
    }

    public function test_listing_cards_carry_every_field_of_a_work(): void
    {
        $result = $this->listing();

        self::assertCount(6, $result->items);
        self::assertSame(self::SIRO, $result->items[0]->url);

        $card = $result->items[0]->meta['movie'];
        self::assertSame('SIRO-5767', $card['external_id']);
        self::assertSame('SIRO-5767', $card['code']);
        self::assertStringEndsWith('【初撮り】ネットでAV応募→AV体験撮影 2649', $card['title']);
        self::assertSame('るな 26歳 ガソリンスタンド店員', $card['performers'][0]['name']);
        self::assertSame(
            'https://www.mgstage.com/search/cSearch.php?actor[]=%E3%82%8B%E3%81%AA%2026%E6%AD%B3%20%E3%82%AC%E3%82%BD%E3%83%AA%E3%83%B3%E3%82%B9%E3%82%BF%E3%83%B3%E3%83%89%E5%BA%97%E5%93%A1&type=top',
            $card['performers'][0]['url'],
        );

        $meta = $card['metadata'];
        self::assertSame(['score' => 4.5, 'scale' => 5.0, 'votes' => 4], $meta['rating']);
        self::assertSame(348, $meta['favorite_count']);
        self::assertSame(546.0, $meta['price']['amount']);
        self::assertSame('JPY', $meta['price']['currency']);
        self::assertSame(780.0, $meta['original_price']['amount']);
        self::assertSame('30%OFF', $meta['discount_label']);
        self::assertSame('2026-11-10T10:00:00+09:00', $meta['sale_ends_at']);
        self::assertSame('https://www.mgstage.com/sampleplayer/sampleplayer.html/56d253a6-5c48-40c5-8859-6a848f9f16f0', $meta['sample_player_url']);
        self::assertSame(['dokusen'], $meta['badges']);
    }

    public function test_listing_card_without_reviews_or_sale_has_no_rating_or_discount(): void
    {
        $meta = $this->listing()->items[4]->meta['movie']['metadata'];

        self::assertArrayNotHasKey('rating', $meta);
        self::assertArrayNotHasKey('discount_label', $meta);
        self::assertArrayNotHasKey('original_price', $meta);
        self::assertSame(400.0, $meta['price']['amount']);
        self::assertSame(16, $meta['favorite_count']);
        self::assertSame(['latest'], $meta['badges']);
    }

    public function test_listing_pagination_reads_current_next_and_last_page(): void
    {
        $pagination = $this->listing()->pagination;

        self::assertSame(1, $pagination->currentPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertSame(334, $pagination->lastPage);
        self::assertTrue($pagination->hasNextPage);
        self::assertSame('https://www.mgstage.com/search/cSearch.php?search_word=&sort=new&list_cnt=30&type=top&page=2', $pagination->nextUrl);
    }

    public function test_the_last_page_has_no_next_page(): void
    {
        $html = $this->loadFixture('mgstage/listing-new.html');
        $html = (string) preg_replace('#<strong class="page">1</strong>#', '<strong class="page">334</strong>', $html);
        $html = (string) preg_replace('#<a class="page" href="[^"]*page=2">次へ</a>#', '', $html);

        $pagination = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::LIST . '&page=334', type: CrawlType::Listing, page: 334))
            ->pagination;

        self::assertSame(334, $pagination->currentPage);
        self::assertNull($pagination->nextUrl);
        self::assertFalse($pagination->hasNextPage);
    }

    public function test_a_listing_without_cards_is_rejected(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><div class="search_list"></div></body></html>')))
            ->execute(new CrawlRequestDto(url: self::LIST, type: CrawlType::Listing));
    }

    private function listing(): \JOOservices\CrawlerX\Dto\CrawlListResultDto
    {
        return (new Listing($this->clientWithFixture('mgstage/listing-new.html')))
            ->execute(new CrawlRequestDto(url: self::LIST, type: CrawlType::Listing));
    }

    /** @return array<string, mixed> */
    private function detail(string $fixture, string $url, bool $withSampleAnswer = false): array
    {
        $client = $this->clientWithFixture('mgstage/' . $fixture);
        if ($withSampleAnswer) {
            $client = $this->clientWithMappedResponses([
                $url => ['body' => $this->loadFixture('mgstage/' . $fixture), 'headers' => ['Content-Type' => 'text/html']],
                'https://www.mgstage.com/sampleplayer/sampleRespons.php?pid=56d253a6-5c48-40c5-8859-6a848f9f16f0' => ['body' => $this->loadFixture('mgstage/sample-siro-5767.json')],
            ]);
        }

        return (new Detail($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail))->meta['movie'];
    }
}
