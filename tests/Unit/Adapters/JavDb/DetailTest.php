<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavDb;

use JOOservices\CrawlerX\Adapters\JavDb\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavDb\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class DetailTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/javdb';

    public function test_the_live_page_fixture_yields_every_field_the_page_offers(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . '/detail.html'))['movie'];

        self::assertSame('a8yV0p', $movie['external_id']);
        self::assertSame('SONE-763', $movie['code']);
        self::assertSame('普段は物静かな文系の美女におち●ぽ調教されちゃう 河北彩伽 （ブルーレイディスク）', $movie['title']);
        self::assertSame('https://c0.jdbstatic.com/covers/a8/a8yV0p.jpg', $movie['cover_url']);
        self::assertSame('2025-06-24', $movie['date']);
        self::assertSame(160, $movie['duration']);
        self::assertSame(['Various Professions', 'Slut', 'Solowork', 'Cowgirl', 'Drama', 'Dirty Words'], $movie['tags']);

        self::assertSame(['EvkJ', 'QVnaK'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['河北彩花', 'フランクフルト林'], array_column($movie['performers'], 'name'));
        self::assertSame('https://javdb.com/actors/EvkJ', $movie['performers'][0]['url']);
        self::assertSame('female', $movie['performers'][0]['metadata']['gender']);
        self::assertSame('male', $movie['performers'][1]['metadata']['gender']);

        $meta = $movie['metadata'];
        self::assertSame('S1 NO.1 STYLE', $meta['maker']);
        self::assertSame('宝瀬博教', $meta['director']);
        self::assertSame(['score' => 4.21, 'scale' => 5.0, 'votes' => 1951], $meta['rating']);
        self::assertSame(6602, $meta['want_count']);
        self::assertSame(1951, $meta['watched_count']);
        self::assertSame('2025-06-24', $meta['release_date_raw']);

        self::assertCount(11, $movie['screenshots']);
        self::assertSame('https://c0.jdbstatic.com/samples/a8/a8yV0p_l_0.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://c0.jdbstatic.com/samples/a8/a8yV0p_s_0.jpg', $movie['screenshots'][0]['thumbnail_url']);

        self::assertCount(18, $meta['magnets']);
        $first = $meta['magnets'][0];
        self::assertSame('SONE-763-C.torrent', $first['name']);
        self::assertStringStartsWith('magnet:?xt=urn:btih:308b2fc4581dbc17bc08c16c2cf1e9f6ba4e049f', $first['url']);
        self::assertSame('8.03GB', $first['size_label']);
        self::assertSame('2025-06-27', $first['date']);
        self::assertSame(['HD', 'Subtitle'], $first['tags']);
        self::assertSame(5, $meta['magnets'][1]['files_count']);
    }

    public function test_no_navigation_item_or_related_movie_is_returned_as_data(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . '/detail.html'))['movie'];

        foreach (['Censored', 'Uncensored', 'Western', 'Tags'] as $noise) {
            self::assertNotContains($noise, array_column($movie['performers'], 'name'));
            self::assertNotContains($noise, $movie['tags']);
        }
        foreach ($movie['screenshots'] as $screenshot) {
            self::assertStringContainsString('/samples/', $screenshot['url']);
        }
    }

    public function test_label_series_and_unknown_rows_are_kept_and_missing_sections_are_absent(): void
    {
        $html = '<html><body><h2 class="title"><strong>AAA-001 </strong><strong class="current-title">Title</strong></h2>'
            . '<nav class="movie-panel-info">'
            . '<div class="panel-block"><strong>ID:</strong><span class="value"><a class="copy-to-clipboard" data-clipboard-text="AAA-001"></a>AAA-001</span></div>'
            . '<div class="panel-block"><strong>Released Date:</strong> <span class="value">2026-01-02</span></div>'
            . '<div class="panel-block"><strong>Series:</strong> <span class="value"><a href="/series/1">Series One</a></span></div>'
            . '<div class="panel-block"><strong>Label:</strong> <span class="value">Label One</span></div>'
            . '<div class="panel-block"><strong>Extra Thing:</strong> <span class="value">extra value</span></div>'
            . '</nav></body></html>';

        $movie = $this->detail($html)['movie'];

        self::assertSame('AAA-001', $movie['code']);
        self::assertSame('Title', $movie['title']);
        self::assertSame('Series One', $movie['metadata']['series']);
        self::assertSame('Label One', $movie['metadata']['label']);
        self::assertSame(['Extra Thing' => 'extra value'], $movie['metadata']['fields']);
        self::assertArrayNotHasKey('magnets', $movie['metadata']);
        self::assertArrayNotHasKey('rating', $movie['metadata']);
        self::assertSame([], $movie['performers']);
        self::assertSame('2026-01-02', $movie['date']);
    }

    public function test_chinese_labels_are_read_as_the_same_fields(): void
    {
        $html = '<html><body><h2 class="title"><strong>SONE-763 </strong><strong class="current-title">標題</strong></h2>'
            . '<nav class="movie-panel-info">'
            . '<div class="panel-block"><strong>番號:</strong><span class="value">SONE-763</span></div>'
            . '<div class="panel-block"><strong>日期:</strong> <span class="value">2025-06-24</span></div>'
            . '<div class="panel-block"><strong>時長:</strong> <span class="value">160 分鍾</span></div>'
            . '<div class="panel-block"><strong>導演:</strong> <span class="value"><a href="/directors/n">宝瀬博教</a></span></div>'
            . '<div class="panel-block"><strong>片商:</strong> <span class="value"><a href="/makers/7R">S1 NO.1 STYLE</a></span></div>'
            . '<div class="panel-block"><strong>評分:</strong> <span class="value"> 4.22分, 由1985人評價</span></div>'
            . '<div class="panel-block"><strong>類別:</strong> <span class="value"><a href="/tags?c=1">蕩婦</a>, <a href="/tags?c=2">戲劇</a></span></div>'
            . '<div class="panel-block"><strong>演員:</strong> <span class="value"><a href="/actors/EvkJ">河北彩花</a><strong class="symbol female">♀</strong></span></div>'
            . '<div class="panel-block"><span class="is-size-7">6602人想看, 1951人看過</span></div>'
            . '</nav></body></html>';

        $movie = $this->detail($html)['movie'];

        self::assertSame('2025-06-24', $movie['date']);
        self::assertSame(160, $movie['duration']);
        self::assertSame('宝瀬博教', $movie['metadata']['director']);
        self::assertSame('S1 NO.1 STYLE', $movie['metadata']['maker']);
        self::assertSame(['score' => 4.22, 'scale' => 5.0, 'votes' => 1985], $movie['metadata']['rating']);
        self::assertSame(['蕩婦', '戲劇'], $movie['tags']);
        self::assertSame('河北彩花', $movie['performers'][0]['name']);
        self::assertSame(6602, $movie['metadata']['want_count']);
        self::assertSame(1951, $movie['metadata']['watched_count']);
        self::assertArrayNotHasKey('fields', $movie['metadata']);
    }

    public function test_the_listing_fixture_yields_code_title_cover_rating_date_and_flags(): void
    {
        $result = $this->listing((string) file_get_contents(self::FIXTURES . '/listing.html'));

        self::assertCount(40, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('0eE9xJ', $movie['external_id']);
        self::assertSame('MAG-088', $movie['code']);
        self::assertSame('有名女優と中出しマッチング 友田彩也香', $movie['title']);
        self::assertSame('https://c0.jdbstatic.com/covers/0e/0eE9xJ.jpg', $movie['cover_url']);
        self::assertSame('2026-09-01', $movie['date']);
        self::assertSame(['score' => 3.0, 'scale' => 5.0, 'votes' => 11], $movie['metadata']['rating']);
        self::assertSame(['DL', 'Today'], $movie['metadata']['flags']);
        self::assertSame('https://javdb.com/v/0eE9xJ', $result->items[0]->url);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame('https://javdb.com/?page=2', $result->pagination->nextUrl);
    }

    public function test_a_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        $this->listing('<html><body><div class="movie-list"></div></body></html>');
    }

    /** @return array<string, mixed> */
    private function detail(string $html): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://javdb.com/v/a8yV0p', type: CrawlType::Detail))
            ->meta;
    }

    private function listing(string $html): \JOOservices\CrawlerX\Dto\CrawlListResultDto
    {
        return (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://javdb.com/', type: CrawlType::Listing));
    }
}
