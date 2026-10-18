<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Duga;

use JOOservices\CrawlerX\Adapters\Duga\Types\Detail;
use JOOservices\CrawlerX\Adapters\Duga\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/duga/';

    public function test_the_product_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . 'detail.html'));

        self::assertSame('vrpandemic-0023', $movie['external_id']);
        self::assertSame('VRPD-008', $movie['code']);
        self::assertSame('完食の美学 どうして女は女の爆盛りウンコを爆喰いするのか？', $movie['title']);
        self::assertStringStartsWith('前代未聞の食糞モンスター爆誕！', $movie['description']);
        self::assertStringNotContainsString('DUGAは', $movie['description']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/jacket_240.jpg', $movie['cover_url']);
        self::assertSame('2026-08-14', $movie['date']);
        self::assertSame(49, $movie['duration']);
        self::assertSame(['あだちねね', '狛稚あやめ'], array_column($movie['performers'], 'name'));
        self::assertSame('https://duga.jp/keyword/%E3%81%82%E3%81%A0%E3%81%A1%E3%81%AD%E3%81%AD', $movie['performers'][0]['url']);
        self::assertSame(['スカトロ'], $movie['tags']);

        $meta = $movie['metadata'];
        self::assertSame('V&Rプランニング', $meta['maker']);
        self::assertSame('V&R PANDEMIC', $meta['label']);
        self::assertSame('安達かおる', $meta['director']);
        self::assertSame('vrpandemic-0023', $meta['product_id']);
        self::assertSame('VRPD-008', $meta['maker_product_number']);
        self::assertSame(['score' => 5.0, 'scale' => 5.0, 'votes' => 1], $meta['rating']);
        self::assertSame(214, $meta['favorite_count']);
        self::assertSame('https://affsample.duga.jp/unsecure/vrpandemic-0023/noauth/movie.mp4', $meta['sample_video_url']);
        self::assertSame('2026年08月14日', $meta['release_date_raw']);
        self::assertSame('2026-08-14', $meta['distribution_start_date']);
        self::assertArrayNotHasKey('distribution_end_date', $meta);
        self::assertSame('https://duga.jp/search/=/directorid=12491/', $meta['director_url']);
        self::assertSame('https://duga.jp/search/=/labelid=vrpandemic/', $meta['label_url']);
        self::assertSame([['アダルト', 'スカトロ']], $meta['categories']);
        self::assertSame([['rank' => 1, 'name' => 'スカトロ', 'url' => 'https://duga.jp/ranking/=/category=04/'], ['rank' => 2, 'name' => '総合ランキング', 'url' => 'https://duga.jp/ranking/']], $meta['rankings']);
        self::assertArrayNotHasKey('fields', $meta);

        self::assertSame(['amount' => 4480.0, 'currency' => 'JPY', 'label' => 'HD版'], $meta['price']);
        self::assertSame([4480.0, 3980.0], array_column($meta['prices'], 'amount'));
        self::assertSame(['HD版', '通常版'], array_column($meta['prices'], 'label'));

        self::assertCount(5, $meta['downloads']);
        self::assertSame('1920×1080pixel', $meta['downloads'][0]['resolution']);
        self::assertSame('6,000kbps', $meta['downloads'][0]['bitrate']);
        self::assertSame('2.06GB', $meta['downloads'][0]['size_label']);
        self::assertSame('HD版', $meta['downloads'][0]['edition']);
        self::assertSame('通常版', $meta['downloads'][3]['edition']);
        self::assertSame('MP4（H.264/AVC）', $meta['downloads'][0]['format']);

        self::assertCount(10, $movie['screenshots']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/cap/0001.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/scap/0001.jpg', $movie['screenshots'][0]['thumbnail_url']);
    }

    public function test_no_navigation_link_is_returned_as_a_tag_or_performer(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . 'detail.html'));

        foreach (['レーベル一覧', 'カテゴリランキング', 'シリーズランキング'] as $noise) {
            self::assertNotContains($noise, $movie['tags']);
            self::assertNotContains($noise, array_column($movie['performers'], 'name'));
        }
    }

    public function test_series_and_unknown_rows_are_kept(): void
    {
        $html = '<html><body><h1 class="title">Title</h1><table class="infomation">'
            . '<tr><th>発売日</th><td><span itemprop="releaseDate" content="2026-01-02">2026年01月02日</span></td></tr>'
            . '<tr><th>シリーズ</th><td><a href="/series/1">Series One</a></td></tr>'
            . '<tr><th>配信終了日</th><td>2026年12月31日</td></tr>'
            . '<tr><th>特記</th><td>extra</td></tr>'
            . '</table></body></html>';

        $movie = $this->detail($html);

        self::assertSame('2026-01-02', $movie['date']);
        self::assertSame('Series One', $movie['metadata']['series']);
        self::assertSame('2026-12-31', $movie['metadata']['distribution_end_date']);
        self::assertSame(['特記' => 'extra'], $movie['metadata']['fields']);
        self::assertSame([], $movie['performers']);
    }

    public function test_ranking_cards_carry_title_cover_rank_favorites_and_price(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing-ranking.html'))))
            ->execute(new CrawlRequestDto(url: 'https://duga.jp/ranking/', type: CrawlType::Listing));

        self::assertCount(100, $result->items);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('flower-0835', $first['external_id']);
        self::assertSame('エロ顔ノーハンドフェラ61', $first['title']);
        self::assertSame('https://pic.duga.jp/unsecure/flower/0835/noauth/jacket_120.jpg', $first['cover_url']);
        self::assertSame(1, $first['metadata']['rank']);
        self::assertSame(13, $first['metadata']['favorite_count']);
        self::assertSame(400.0, $first['metadata']['price']['amount']);
        self::assertArrayNotHasKey('rating', $first['metadata']);
        self::assertSame('https://duga.jp/ppv/flower-0835/', $result->items[0]->url);
    }

    public function test_the_home_page_is_read_from_the_ranking_page_and_its_skeleton_cards_are_ignored(): void
    {
        $skeleton = '<html><body><div class="contentslist empty"><div class="image"></div><div class="title"></div></div></body></html>';

        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml($skeleton)))
            ->execute(new CrawlRequestDto(url: 'https://duga.jp/main/', type: CrawlType::Listing));
    }

    public function test_rated_cards_with_a_score_in_the_image_name_or_alt_text_are_rated(): void
    {
        $html = '<html><body>'
            . '<div class="contentslist"><a href="/ppv/a-0001/"><div class="image"><img src="https://pic.duga.jp/a.jpg" alt="Alpha"></div><div class="title">Alpha</div></a><div class="ratingstar"><img src="https://img.duga.jp/img/contents/score/4.5.gif" alt="Rating-Score"></div></div>'
            . '<div class="contentslist"><a href="/ppv/b-0002/"><div class="image"><img src="https://pic.duga.jp/b.jpg" alt="Beta"></div><div class="title">Beta</div></a><div class="ratingstar"><img src="https://img.duga.jp/img/contents/score/3.0.gif" alt="3.0点"></div></div>'
            . '</body></html>';

        $result = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://duga.jp/ranking/', type: CrawlType::Listing));

        self::assertSame(4.5, $result->items[0]->meta['movie']['metadata']['rating']['score']);
        self::assertSame(3.0, $result->items[1]->meta['movie']['metadata']['rating']['score']);
    }

    public function test_a_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body></body></html>')))
            ->execute(new CrawlRequestDto(url: 'https://duga.jp/ranking/', type: CrawlType::Listing));
    }

    /** @return array<string, mixed> */
    private function detail(string $html): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://duga.jp/ppv/vrpandemic-0023/', type: CrawlType::Detail))
            ->meta['movie'];
    }
}
