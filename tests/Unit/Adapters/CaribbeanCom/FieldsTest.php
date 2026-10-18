<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\CaribbeanCom;

use JOOservices\CrawlerX\Adapters\CaribbeanCom\Types\Detail;
use JOOservices\CrawlerX\Adapters\CaribbeanCom\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/caribbeancom/';

    public function test_the_movie_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . 'detail.html'));

        self::assertSame('080826-001', $movie['external_id']);
        self::assertSame('080826-001', $movie['code']);
        self::assertSame('Luxury Adult Healing Spa: Orgasm after orgasm! Thrusting in cowgirl position.', $movie['title']);
        self::assertSame('https://en.caribbeancom.com/moviepages/080826-001/images/poster_en.jpg', $movie['cover_url']);
        self::assertSame('2026-08-08', $movie['date']);
        self::assertSame(62, $movie['duration']);
        self::assertSame(
            ['original', 'nicetitties', 'creampie', 'kyonyu', 'paipan', 'tittyfuck', 'handjob', 'sixtynine', 'eatpussy', 'slender', 'kounaihassha', 'bikyaku', 'bishiri', 'yokui'],
            $movie['tags'],
        );

        self::assertCount(1, $movie['performers']);
        self::assertSame('8919', $movie['performers'][0]['external_id']);
        self::assertSame('Yukino Shirasaki', $movie['performers'][0]['name']);
        self::assertSame('https://en.caribbeancom.com/eng/search_act/8919/1.html', $movie['performers'][0]['url']);
        self::assertNotContains('AV Idols', array_column($movie['performers'], 'name'));

        $meta = $movie['metadata'];
        self::assertSame('2026/08/08', $meta['release_date_raw']);
        self::assertSame('https://smovie.caribbeancom.com/sample/movies/080826-001/480p.mp4', $meta['sample_video_url']);
        self::assertArrayNotHasKey('fields', $meta);

        self::assertCount(5, $meta['downloads']);
        self::assertSame('1080p Full HD', $meta['downloads'][0]['quality']);
        self::assertSame('1.78 GB', $meta['downloads'][0]['size_label']);
        self::assertSame('https://dl21en.caribbeancom.com/member/movies/080826-001/1080p.mp4', $meta['downloads'][0]['url']);
        self::assertSame('member', $meta['downloads'][0]['type']);
        self::assertSame(['1080p Full HD', '720p HD', '480p', '360p', '240p'], array_column($meta['downloads'], 'quality'));

        self::assertCount(40, $movie['screenshots']);
        self::assertSame('https://en.caribbeancom.com/moviepages/080826-001/images/l/001.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://en.caribbeancom.com/moviepages/080826-001/images/s/001.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertTrue($movie['screenshots'][0]['metadata']['is_sample']);
        self::assertFalse($movie['screenshots'][3]['metadata']['is_sample']);
    }

    public function test_the_server_rendered_page_still_gives_cover_and_sample_videos(): void
    {
        $html = '<html><body><script>var Movie = {"movie_id":"080826-001","sample_flash_url":"https:\/\/smovie.caribbeancom.com\/sample\/movies\/080826-001\/480p.mp4","sample_m_flash_url":"https:\/\/smovie.caribbeancom.com\/sample\/movies\/080826-001\/sample_m.mp4"};</script>'
            . '<script>var image = "/moviepages/"+movie_id+"/images/poster_en.jpg";</script>'
            . '<div class="movie-info"><h1 itemprop="name">Title</h1><ul><li class="movie-detail__spec"><span class="spec-title">Release Date:</span><span class="spec-content">2026/08/08</span></li></ul></div></body></html>';

        $movie = $this->detail($html);

        self::assertSame('https://en.caribbeancom.com/moviepages/080826-001/images/poster_en.jpg', $movie['cover_url']);
        self::assertSame('https://smovie.caribbeancom.com/sample/movies/080826-001/480p.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('https://smovie.caribbeancom.com/sample/movies/080826-001/sample_m.mp4', $movie['metadata']['sample_mobile_video_url']);
    }

    public function test_unknown_spec_rows_are_kept(): void
    {
        $html = '<html><body><div class="movie-info"><h1 itemprop="name">Title</h1><ul>'
            . '<li class="movie-detail__spec"><span class="spec-title">Release Date:</span><span class="spec-content">2026/01/02</span></li>'
            . '<li class="movie-detail__spec"><span class="spec-title">Series:</span><span class="spec-content">Some Series</span></li>'
            . '</ul></div></body></html>';

        $movie = $this->detail($html);

        self::assertSame('2026-01-02', $movie['date']);
        self::assertSame(['Series' => 'Some Series'], $movie['metadata']['fields']);
        self::assertSame([], $movie['performers']);
        self::assertArrayNotHasKey('downloads', $movie['metadata']);
    }

    public function test_listing_cards_carry_title_cover_date_and_performers(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing.html'))))
            ->execute(new CrawlRequestDto(url: 'https://en.caribbeancom.com/eng/index2.htm', type: CrawlType::Listing));

        self::assertCount(20, $result->items);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('090826-001', $first['external_id']);
        self::assertSame('Pussy Encyclopedia: Haruka Ayano', $first['title']);
        self::assertSame('https://en.caribbeancom.com/moviepages/090826-001/images/thum_sh.jpg', $first['cover_url']);
        self::assertSame('2026-09-08', $first['date']);
        self::assertSame('8900', $first['performers'][0]['external_id']);
        self::assertSame('Haruka Ayano', $first['performers'][0]['name']);
        self::assertSame('https://en.caribbeancom.com/eng/moviepages/090826-001/index.html', $result->items[0]->url);
    }

    public function test_a_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body></body></html>')))
            ->execute(new CrawlRequestDto(url: 'https://en.caribbeancom.com/eng/index2.htm', type: CrawlType::Listing));
    }

    /** @return array<string, mixed> */
    private function detail(string $html): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://en.caribbeancom.com/eng/moviepages/080826-001/index.html', type: CrawlType::Detail))
            ->meta['movie'];
    }
}
