<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\CaribbeanComPr;

use JOOservices\CrawlerX\Adapters\CaribbeanCom\Profile;
use JOOservices\CrawlerX\Adapters\CaribbeanCom\Types\Detail;
use JOOservices\CrawlerX\Adapters\CaribbeanCom\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/caribbeancompr/';

    private const EN_URL = 'https://en.caribbeancompr.com/eng/moviepages/060126_001/index.html';

    private const JA_URL = 'https://www.caribbeancompr.com/moviepages/060126_001/index.html';

    public function test_the_english_movie_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . 'detail.html'), self::EN_URL);

        self::assertSame('060126_001', $movie['external_id']);
        self::assertSame('060126_001', $movie['code']);
        self::assertSame('We got frisky in Kimono before heading out to the summer festival', $movie['title']);
        self::assertSame('https://en.caribbeancompr.com/moviepages/060126_001/images/poster_en.jpg', $movie['cover_url']);
        self::assertSame('2026-06-01', $movie['date']);
        self::assertSame(61, $movie['duration']);
        self::assertSame(['Amateur', 'Pretty Tits', 'Creampie', 'Big Tits', 'Shaved Pussy', 'Blowjob', 'Bareback'], $movie['tags']);
        self::assertNull($movie['description']);

        self::assertCount(1, $movie['performers']);
        self::assertSame('Mirina Nozomi', $movie['performers'][0]['name']);
        self::assertSame('https://en.caribbeancompr.com/eng/search/Mirina+Nozomi/1.html', $movie['performers'][0]['url']);
        self::assertNull($movie['performers'][0]['external_id']);

        $meta = $movie['metadata'];
        self::assertSame('2026-06-01', $meta['release_date_raw']);
        self::assertSame('https://smovie.caribbeancompr.com/sample/movies/060126_001/480p.mp4', $meta['sample_video_url']);
        self::assertSame('38036', $meta['movie_seq']);
        self::assertTrue($meta['flags']['has_gallery']);
        self::assertTrue($meta['flags']['has_streaming']);
        self::assertFalse($meta['flags']['is_presale']);
        self::assertFalse($meta['flags']['is_movie_expired']);
        self::assertSame(['amount' => 24.6, 'currency' => 'USD', 'label' => 'Purchase(Unlimited)'], $meta['price']);
        self::assertSame(['amount' => 6.15, 'currency' => 'USD', 'label' => 'Rental(10 Days)'], $meta['rental_price']);
        self::assertSame(['1080p', '720p', '480p', '360p', '240p'], $meta['available_resolutions']);
        self::assertArrayNotHasKey('maker', $meta);
        self::assertArrayNotHasKey('fields', $meta);

        self::assertCount(5, $meta['downloads']);
        self::assertSame(['1080p Full HD', '720p HD', '480p', '360p', '240p'], array_column($meta['downloads'], 'quality'));
        self::assertSame(['1.74 GB', '588 MB', '436 MB', '219 MB', '176 MB'], array_column($meta['downloads'], 'size_label'));
        self::assertSame('https://dl11.caribbeancompr.com/member/movies/060126_001/060126_001-caribpr-1080p.mp4', $meta['downloads'][0]['url']);
        self::assertSame('member', $meta['downloads'][0]['type']);

        self::assertCount(31, $movie['screenshots']);
        self::assertSame('https://www.caribbeancompr.com/moviepages/060126_001/images/l/001.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://en.caribbeancompr.com/moviepages/060126_001/images/s/001.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertTrue($movie['screenshots'][0]['metadata']['is_sample']);
        self::assertFalse($movie['screenshots'][3]['metadata']['is_sample']);
    }

    public function test_the_japanese_movie_page_adds_studio_description_and_japanese_tags(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . 'detail-ja.html'), self::JA_URL);

        self::assertSame('060126_001', $movie['external_id']);
        self::assertSame('お祭りへ出かける前に浴衣でエッチしちゃった', $movie['title']);
        self::assertStringStartsWith('夏が来る', (string) $movie['description']);
        self::assertStringEndsWith('浴衣デートしたいなあ！', (string) $movie['description']);
        self::assertSame('https://www.caribbeancompr.com/moviepages/060126_001/images/l_l.jpg', $movie['cover_url']);
        self::assertSame('2026-06-01', $movie['date']);
        self::assertSame(61, $movie['duration']);
        self::assertSame(['素人', '美乳', '中出し', '巨乳', 'パイパン', 'フェラチオ', '生ハメ・生姦', '美尻', '美脚'], $movie['tags']);
        self::assertSame('希望みりな', $movie['performers'][0]['name']);
        self::assertSame('https://www.caribbeancompr.com/search/%B4%F5%CB%BE%A4%DF%A4%EA%A4%CA/1.html', $movie['performers'][0]['url']);

        $meta = $movie['metadata'];
        self::assertSame('天然むすめ', $meta['maker']);
        self::assertSame('2026-06-01', $meta['release_date_raw']);
        self::assertSame('https://smovie.caribbeancompr.com/sample/movies/060126_001/480p.mp4', $meta['sample_video_url']);
        self::assertSame('38036', $meta['movie_seq']);
        self::assertSame(24.6, $meta['price']['amount']);
        self::assertSame('購入（無期限ストリーミング）', $meta['price']['label']);
        self::assertSame(6.15, $meta['rental_price']['amount']);
        self::assertSame('レンタル', $meta['rental_price']['label']);
        self::assertSame(['1080p', '720p', '480p', '360p', '240p'], $meta['available_resolutions']);
        self::assertSame(['1080p Full HD', '720p HD', '480p', '360p', '240p'], array_column($meta['downloads'], 'quality'));
        self::assertSame(['1.74 GB', '588 MB', '436 MB', '219 MB', '176 MB'], array_column($meta['downloads'], 'size_label'));
        self::assertStringStartsWith('https://dl', $meta['downloads'][0]['url']);
        self::assertArrayNotHasKey('fields', $meta);
        self::assertCount(31, $movie['screenshots']);
    }

    public function test_a_page_already_decoded_to_utf8_but_declaring_euc_jp_is_still_read(): void
    {
        $utf8 = (string) iconv('EUC-JP', 'UTF-8//IGNORE', (string) file_get_contents(self::FIXTURES . 'detail-ja.html'));

        $movie = $this->detail($utf8, self::JA_URL);

        self::assertSame('お祭りへ出かける前に浴衣でエッチしちゃった', $movie['title']);
        self::assertSame('天然むすめ', $movie['metadata']['maker']);
    }

    public function test_unknown_spec_rows_are_kept_and_a_page_without_a_purchase_box_has_no_prices(): void
    {
        $html = '<html><body><div class="movie-info"><div class="section"><h1>Title</h1><ul>'
            . '<li class="movie-spec"><span class="spec-title">Release Date</span><span class="spec-content">2026-01-02</span></li>'
            . '<li class="movie-spec"><span class="spec-title">Series</span><span class="spec-content">Some Series</span></li>'
            . '</ul></div></div></body></html>';

        $movie = $this->detail($html, self::EN_URL);

        self::assertSame('2026-01-02', $movie['date']);
        self::assertSame(['Series' => 'Some Series'], $movie['metadata']['fields']);
        self::assertArrayNotHasKey('price', $movie['metadata']);
        self::assertArrayNotHasKey('flags', $movie['metadata']);
    }

    public function test_listing_cards_carry_title_cover_price_and_pagination(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing.html')), Profile::caribbeancompr()))
            ->execute(new CrawlRequestDto(url: 'https://en.caribbeancompr.com/eng/listpages/1.html', type: CrawlType::Listing));

        self::assertCount(40, $result->items);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('110226_001', $first['external_id']);
        self::assertSame('110226_001', $first['code']);
        self::assertSame('A cosplay delivery health girl who will let you cum inside her : Cheer Leader', $first['title']);
        self::assertSame('https://en.caribbeancompr.com/moviepages/110226_001/images/main_s.jpg', $first['cover_url']);
        self::assertSame(['amount' => 6.15, 'currency' => 'USD', 'label' => '$6.15 ~'], $first['metadata']['price']);
        self::assertArrayNotHasKey('original_price', $first['metadata']);
        self::assertSame('https://en.caribbeancompr.com/eng/moviepages/110226_001/index.html', $result->items[0]->url);

        $sale = array_values(array_filter($result->items, static fn($item): bool => $item->meta['movie']['external_id'] === '102826_001'))[0]->meta['movie'];
        self::assertSame(['amount' => 5.95, 'currency' => 'USD', 'label' => '$5.95 ~'], $sale['metadata']['price']);
        self::assertSame(['amount' => 6.73, 'currency' => 'USD', 'label' => null], $sale['metadata']['original_price']);
        self::assertSame(174, $result->pagination?->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame('https://en.caribbeancompr.com/eng/listpages/2.html', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_a_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body></body></html>'), Profile::caribbeancompr()))
            ->execute(new CrawlRequestDto(url: 'https://en.caribbeancompr.com/eng/listpages/1.html', type: CrawlType::Listing));
    }

    /** @return array<string, mixed> */
    private function detail(string $html, string $url): array
    {
        return (new Detail($this->clientWithHtml($html), Profile::caribbeancompr()))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail))
            ->meta['movie'];
    }
}
