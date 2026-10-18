<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\MinnanoAv;

use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class DetailTest extends TestCase
{
    private const URL = 'https://www.minnano-av.com/av159081.html';

    public function test_the_live_page_fixture_yields_every_field_the_page_carries(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../../../Fixtures/minnanoav/movie-detail.html');

        $movie = $this->execute($html)->meta['movie'];

        self::assertSame('OFJE-704', $movie['code']);
        self::assertStringStartsWith('‘みんなが思う’', $movie['title']);
        self::assertStringNotContainsString('みんなのAV.com', $movie['title']);
        self::assertSame('2026-06-25', $movie['date']);
        self::assertSame('https://www.minnano-av.com/p_package/2605/159081.jpg', $movie['cover_url']);
        self::assertSame('エスワン ナンバーワンスタイル', $movie['metadata']['maker']);
        self::assertSame('S1 NO.1 STYLE', $movie['metadata']['label']);
        self::assertSame('2026年06月 25日', $movie['metadata']['release_date_raw']);
        self::assertStringContainsString('litevideo', $movie['metadata']['sample_video_url']);
        self::assertSame(
            ['125605', '163082', '205870', '383181', '453806'],
            array_column($movie['performers'], 'external_id'),
        );
        self::assertSame('瀬戸環奈', $movie['performers'][0]['name']);
        self::assertSame('https://www.minnano-av.com/actress125605.html', $movie['performers'][0]['url']);
    }

    public function test_a_page_without_the_info_table_keeps_the_title_and_the_site_code(): void
    {
        $html = '<html><head><title>Fallback - みんなのAV.com</title></head><body><h1 class="title">Only a title</h1></body></html>';

        $movie = $this->execute($html)->meta['movie'];

        self::assertSame('Only a title', $movie['title']);
        self::assertSame('AV-159081', $movie['code']);
        self::assertSame([], $movie['performers']);
        self::assertNull($movie['date']);
    }

    public function test_dates_durations_series_and_genres_are_read_from_the_info_table(): void
    {
        $html = '<html><body><h1 class="title">Sample</h1><table class="prof-table">'
            . '<tr><td>発売日</td><td>2026年6月5日</td></tr>'
            . '<tr><td>収録時間</td><td>120分</td></tr>'
            . '<tr><td>シリーズ</td><td>Series A</td></tr>'
            . '<tr><td>監督</td><td>Director B</td></tr>'
            . '<tr><td>ジャンル</td><td><a href="/g1">One</a>, <a href="/g2">Two</a></td></tr>'
            . '</table></body></html>';

        $movie = $this->execute($html)->meta['movie'];

        self::assertSame('2026-06-05', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame('Series A', $movie['metadata']['series']);
        self::assertSame('Director B', $movie['metadata']['director']);
        self::assertSame(['One', 'Two'], $movie['tags']);
    }

    public function test_filmography_listing_items_carry_their_cover_image(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../../../Fixtures/minnanoav/movie-listing.html');

        $result = (new \JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://www.minnano-av.com/actress.php?actress_id=945093', type: CrawlType::Listing));

        $withCover = array_filter($result->items, static fn($item): bool => is_string($item->meta['movie']['cover_url'] ?? null));
        self::assertNotEmpty($result->items);
        self::assertNotEmpty($withCover);
        self::assertStringStartsWith('https://www.minnano-av.com/p_package/', array_values($withCover)[0]->meta['movie']['cover_url']);
    }

    private function execute(string $html): \JOOservices\CrawlerX\Dto\CrawlItemResultDto
    {
        return (new Detail($this->clientWithHtml($html)))->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Detail));
    }
}
