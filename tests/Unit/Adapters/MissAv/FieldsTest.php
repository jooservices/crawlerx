<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\MissAv;

use JOOservices\CrawlerX\Adapters\Missav\Types\Detail;
use JOOservices\CrawlerX\Adapters\Missav\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const URL = 'https://missav.ws/en/fns-247';

    public function test_the_movie_page_yields_every_labelled_field(): void
    {
        $movie = $this->detail((string) file_get_contents(__DIR__ . '/../../../Fixtures/missav/detail-fns-247.html'));

        self::assertSame('FNS-247', $movie['code']);
        self::assertStringStartsWith('FNS-247 My tomboyish older sister', $movie['title']);
        self::assertSame('https://fourhoi.com/fns-247/cover-n.jpg', $movie['cover_url']);
        self::assertSame('2026-08-29', $movie['date']);
        self::assertSame(172, $movie['duration']);
        self::assertSame(['Sister', 'Individual', 'Ride', 'Oral Sex', 'Squirting', 'Tit Job', 'Hd'], $movie['tags']);

        self::assertSame(['Tsubasa Mai'], array_column($movie['performers'], 'name'));
        self::assertSame('Tsubasa Mai', $movie['performers'][0]['external_id']);
        self::assertSame('https://missav.ws/dm41/en/actresses/Tsubasa%20Mai', $movie['performers'][0]['url']);

        $meta = $movie['metadata'];
        self::assertSame('FALENO', $meta['maker']);
        self::assertSame('FALENO star', $meta['label']);
        self::assertSame('矢澤レシーブ', $meta['director']);
        self::assertSame('ボーイッシュな姉は隠れ巨乳ナイスボディで密着スキンシップに興奮してしまった僕。 つばさ舞', $meta['original_title']);
        self::assertSame('https://missav.ws/dm2/en/makers/FALENO', $meta['maker_url']);
        self::assertSame('https://missav.ws/dm2/en/labels/FALENO%20star', $meta['label_url']);
        self::assertSame('https://surrit.com/9e8a632c-c02a-4a00-9bf5-00672067438f/720p/video.m3u8', $meta['stream_url']);
        self::assertArrayNotHasKey('series', $meta);
    }

    public function test_maker_label_and_director_links_are_not_performers(): void
    {
        $movie = $this->detail((string) file_get_contents(__DIR__ . '/../../../Fixtures/missav/detail-fns-247.html'));

        foreach (['FALENO', 'FALENO star', '矢澤レシーブ'] as $name) {
            self::assertNotContains($name, array_column($movie['performers'], 'name'));
        }
    }

    public function test_a_series_row_and_unknown_rows_are_kept(): void
    {
        $html = '<html><head><meta property="og:video:release_date" content="2020-01-02"></head><body><h1>ABC-123 Title</h1>'
            . '<div class="space-y-2">'
            . '<div class="text-secondary"><span>Code:</span> <span>ABC-123</span></div>'
            . '<div class="text-secondary"><span>Series:</span> <a href="https://missav.ws/dm1/en/series/One">One</a></div>'
            . '<div class="text-secondary"><span>Special:</span> <span>extra</span></div>'
            . '</div></body></html>';

        $movie = $this->detail($html);

        self::assertSame('One', $movie['metadata']['series']);
        self::assertSame('https://missav.ws/dm1/en/series/One', $movie['metadata']['series_url']);
        self::assertSame(['Special' => 'extra'], $movie['metadata']['fields']);
    }

    public function test_listing_cards_carry_cover_duration_and_preview(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(__DIR__ . '/../../../Fixtures/missav/listing-new.html'))))
            ->execute(new CrawlRequestDto(url: 'https://missav.ws/en/new', type: CrawlType::Listing));

        $byId = [];
        foreach ($result->items as $item) {
            $byId[$item->meta['movie']['external_id']] = $item->meta['movie'];
        }

        self::assertSame('https://fourhoi.com/ihd-010/cover-t.jpg', $byId['ihd-010']['cover_url']);
        self::assertSame(66, $byId['ihd-010']['duration']);
        self::assertSame('https://fourhoi.com/ihd-010/preview.mp4', $byId['ihd-010']['metadata']['preview_video_url']);
    }

    /** @return array<string, mixed> */
    private function detail(string $html): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Detail))
            ->meta['movie'];
    }
}
