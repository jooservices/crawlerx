<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\FfJav;

use JOOservices\CrawlerX\Adapters\FfJav\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_torrent_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail((string) file_get_contents(__DIR__ . '/../../../Fixtures/ffjav/detail-sample-1.html'));

        self::assertStringStartsWith('MIDA-768 ', $movie['title']);
        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/mida768/mida768pl.jpg', $movie['cover_url']);
        self::assertSame('2026-09-01', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame(['八木奈々'], array_column($movie['performers'], 'name'));
        self::assertSame('https://ffjav.com/torrent/tag/%e5%85%ab%e6%9c%a8%e5%a5%88%e3%80%85', $movie['performers'][0]['url']);

        $meta = $movie['metadata'];
        self::assertSame('ドラゴン西川', $meta['director']);
        self::assertSame('ムーディーズ', $meta['maker']);
        self::assertSame('MOODYZ DIVA', $meta['label']);
        self::assertSame('2026-09-01', $meta['release_date_raw']);
        self::assertSame('2026-08-28', $meta['posted_date']);
        self::assertSame('https://ffjav.com/download/2026/202608/ffjav.com_mida768.torrent', $meta['download_url']);
        self::assertSame('torrent', $meta['downloads'][0]['type']);
        self::assertSame('5.9 GB', $meta['downloads'][0]['size_label']);
        self::assertSame(6335076762, $meta['downloads'][0]['size_bytes']);
        self::assertArrayNotHasKey('fields', $meta);
    }

    public function test_the_post_date_is_not_the_release_date(): void
    {
        $movie = $this->detail((string) file_get_contents(__DIR__ . '/../../../Fixtures/ffjav/detail-sample-1.html'));

        self::assertNotSame($movie['metadata']['posted_date'], $movie['date']);
    }

    public function test_unknown_rows_are_kept_and_a_missing_release_falls_back_to_the_post_date(): void
    {
        $html = '<html><body><h5 class="title"><a href="/torrent/x">ABC-123 Name</a></h5>'
            . '<p class="subtitle"><a>2026-01-02</a></p>'
            . '<table><tr><td class="header">シリーズ:</td><td class="text">One &nbsp;</td></tr></table>'
            . '<a href="https://ffjav.com/x.torrent">HD 1.5&nbsp;GB</a></body></html>';

        $movie = $this->detail($html);

        self::assertSame('2026-01-02', $movie['date']);
        self::assertSame(['シリーズ' => 'One'], $movie['metadata']['fields']);
    }

    /** @return array<string, mixed> */
    private function detail(string $html): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://ffjav.com/torrent/mida-768', type: CrawlType::Detail))
            ->meta['movie'];
    }
}
