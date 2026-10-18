<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavBus;

use JOOservices\CrawlerX\Adapters\JavBus\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavBus\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/javbus/';

    public function test_the_movie_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail('detail-namh-074-live.html');

        self::assertSame('NAMH-074', $movie['external_id']);
        self::assertSame('NAMH-074', $movie['code']);
        self::assertStringStartsWith('至高の膣内射精アングル19発', $movie['title']);
        self::assertSame('https://www.javbus.com/pics/cover/chuw_b.jpg', $movie['cover_url']);
        self::assertSame('2026-09-17', $movie['date']);
        self::assertSame(145, $movie['duration']);
        self::assertSame(['Slut', 'Threesome / Foursome', 'Beautiful Girl', 'Creampie', 'フルハイビジョン(FHD)'], $movie['tags']);

        self::assertCount(1, $movie['performers']);
        self::assertSame('11p7', $movie['performers'][0]['external_id']);
        self::assertSame('Misaki oto', $movie['performers'][0]['name']);
        self::assertSame('https://www.javbus.com/en/star/11p7', $movie['performers'][0]['url']);

        $meta = $movie['metadata'];
        self::assertSame('なまなま', $meta['maker']);
        self::assertSame('なまなま', $meta['label']);
        self::assertSame('至高の膣内射精', $meta['series']);
        self::assertSame('2026-09-17', $meta['release_date_raw']);
        self::assertArrayNotHasKey('fields', $meta);

        self::assertCount(17, $movie['screenshots']);
        self::assertSame('https://image.mgstage.com/images/namanama/787namh/074/cap_e_0_787namh-074.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://www.javbus.com/pics/sample/chuw_1.jpg', $movie['screenshots'][0]['thumbnail_url']);
    }

    public function test_every_magnet_of_the_table_is_returned_with_size_date_and_flags(): void
    {
        $magnets = $this->detail('detail-namh-074-live.html')['metadata']['magnets'];

        self::assertCount(9, $magnets);
        self::assertSame('NAMH074', $magnets[0]['name']);
        self::assertStringStartsWith('magnet:?xt=urn:btih:fa49e70a48813c66da3eb5eedf1cd404580a2787', $magnets[0]['url']);
        self::assertSame('1.81GB', $magnets[0]['size_label']);
        self::assertSame('2026-09-04', $magnets[0]['date']);
        self::assertSame([], $magnets[0]['tags']);
        self::assertSame(['HD'], $magnets[1]['tags']);
        self::assertSame(count($magnets), count(array_unique(array_column($magnets, 'url'))));
    }

    public function test_other_languages_and_unknown_rows_are_kept(): void
    {
        $html = '<html><body><div class="container"><h3>ABC-123 Some title</h3>'
            . '<div class="info">'
            . '<p><span class="header">識別碼:</span> <span>ABC-123</span></p>'
            . '<p><span class="header">發行日期:</span> 2026-01-02</p>'
            . '<p><span class="header">長度:</span> 120分鐘</p>'
            . '<p><span class="header">導演:</span> <a href="/director/1">Dir One</a></p>'
            . '<p><span class="header">製作商:</span> <a href="/studio/1">Maker One</a></p>'
            . '<p><span class="header">Extra:</span> something</p>'
            . '</div></div></body></html>';

        $movie = $this->detailHtml($html);

        self::assertSame('ABC-123', $movie['code']);
        self::assertSame('Some title', $movie['title']);
        self::assertSame('2026-01-02', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame('Dir One', $movie['metadata']['director']);
        self::assertSame('Maker One', $movie['metadata']['maker']);
        self::assertSame(['Extra' => 'something'], $movie['metadata']['fields']);
        self::assertArrayNotHasKey('magnets', $movie['metadata']);
    }

    public function test_the_performer_page_with_a_profile_yields_every_profile_field_and_the_filmography(): void
    {
        $performer = $this->performer('performer-detail-okq-live.html', 'okq');

        self::assertSame('okq', $performer['external_id']);
        self::assertSame('sanjo*a', $performer['name']);
        self::assertSame('https://www.javbus.com/pics/actress/okq_a.jpg', $performer['profile_image_url']);
        self::assertSame('1993-08-16', $performer['birth_date_raw']);
        self::assertSame('159cm', $performer['height_raw']);
        self::assertSame('B84(F) W58 H88', $performer['size_raw']);
        self::assertSame([], $performer['tags']);

        $meta = $performer['metadata'];
        self::assertSame(33, $meta['age']);
        self::assertSame('F', $meta['cup']);
        self::assertSame('84cm', $meta['bust']);
        self::assertSame('58cm', $meta['waist']);
        self::assertSame('88cm', $meta['hips']);
        self::assertSame('アイドルDVD鑑賞', $meta['hobby']);

        self::assertCount(30, $meta['filmography']);
        self::assertSame('OFJE-667', $meta['filmography'][0]['code']);
        self::assertSame('2026-10-09', $meta['filmography'][0]['date']);
        self::assertSame(['HD'], $meta['filmography'][0]['flags']);
        self::assertSame('https://www.javbus.com/en/OFJE-667', $meta['filmography'][0]['url']);
    }

    public function test_a_performer_without_a_profile_still_lists_the_movies(): void
    {
        $performer = $this->performer('performer-detail-11p7-live.html', '11p7');

        self::assertSame('Misaki oto', $performer['name']);
        self::assertNull($performer['birth_date_raw']);
        self::assertNull($performer['size_raw']);
        self::assertNotEmpty($performer['metadata']['filmography']);
    }

    public function test_listing_cards_carry_title_cover_date_and_flags(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing-live.html'))))
            ->execute(new CrawlRequestDto(url: 'https://www.javbus.com/en/', type: CrawlType::Listing));

        self::assertCount(23, $result->items);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('PPR-010', $first['code']);
        self::assertSame('河合あすな 8時間 BEST PRESTIGE PREMIUM RESTRICTED vol.13', $first['title']);
        self::assertSame('2026-09-18', $first['date']);
        self::assertSame('https://www.javbus.com/pics/thumb/ciys.jpg', $first['cover_url']);

        $flags = array_filter(array_map(static fn($item) => $item->meta['movie']['metadata']['flags'] ?? null, $result->items));
        self::assertNotEmpty($flags);
    }

    public function test_performer_listing_cards_carry_the_avatar(): void
    {
        $result = (new \JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerListing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'performer-listing-live.html'))))
            ->execute(new CrawlRequestDto(url: 'https://www.javbus.com/en/actresses', type: CrawlType::PerformerListing));

        $first = $result->items[0]->meta['performer'];
        self::assertSame('okq', $first['external_id']);
        self::assertSame('sanjo*a', $first['name']);
        self::assertSame('https://www.javbus.com/pics/actress/okq_a.jpg', $first['profile_image_url']);
    }

    /** @return array<string, mixed> */
    private function detail(string $fixture): array
    {
        return $this->detailHtml((string) file_get_contents(self::FIXTURES . $fixture));
    }

    /** @return array<string, mixed> */
    private function detailHtml(string $html): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://www.javbus.com/en/NAMH-074', type: CrawlType::Detail))
            ->meta['movie'];
    }

    /** @return array<string, mixed> */
    private function performer(string $fixture, string $id): array
    {
        return (new PerformerDetail($this->clientWithHtml((string) file_get_contents(self::FIXTURES . $fixture))))
            ->execute(new CrawlRequestDto(url: 'https://www.javbus.com/en/star/' . $id, type: CrawlType::PerformerDetail))
            ->meta['performer'];
    }
}
