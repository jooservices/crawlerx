<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Support;

use JOOservices\CrawlerX\Support\MovieMeta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MovieMetaTest extends TestCase
{
    /** @return iterable<string, array{?string, ?string}> */
    public static function isoDates(): iterable
    {
        yield 'dashes' => ['2026-08-14', '2026-08-14'];
        yield 'slashes' => ['Release 2026/8/4', '2026-08-04'];
        yield 'japanese' => ['2026年08月 14日', '2026-08-14'];
        yield 'dots' => ['2026.10.16', '2026-10-16'];
        yield 'invalid day' => ['2026-02-31', null];
        yield 'no date' => ['coming soon', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('isoDates')]
    public function test_iso_date_reads_year_first_dates(?string $text, ?string $expected): void
    {
        self::assertSame($expected, MovieMeta::isoDate($text));
    }

    public function test_iso_date_month_first_reads_us_style_dates(): void
    {
        self::assertSame('2026-09-01', MovieMeta::isoDateMonthFirst('09/01/2026'));
        self::assertNull(MovieMeta::isoDateMonthFirst('13/45/2026'));
        self::assertNull(MovieMeta::isoDateMonthFirst(null));
        self::assertNull(MovieMeta::isoDateMonthFirst('2026-09-01'));
    }

    /** @return iterable<string, array{?string, ?int}> */
    public static function durations(): iterable
    {
        yield 'minutes japanese' => ['120分', 120];
        yield 'minutes with space' => ['120 min', 120];
        yield 'minutes javdb' => ['160 minute(s)', 160];
        yield 'long clock' => ['01:02:12', 62];
        yield 'rounds seconds' => ['00:48:33', 49];
        yield 'mixed japanese' => ['48分 33秒', 48];
        yield 'short clock' => ['48:33', 49];
        yield 'none' => ['unknown', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('durations')]
    public function test_duration_minutes_reads_common_formats(?string $text, ?int $expected): void
    {
        self::assertSame($expected, MovieMeta::durationMinutes($text));
    }

    public function test_builders_return_the_documented_shapes(): void
    {
        self::assertSame(['score' => 4.5, 'scale' => 5.0, 'votes' => 34], MovieMeta::rating(4.5, 5.0, 34));
        self::assertSame(['score' => 3.0, 'scale' => null, 'votes' => null], MovieMeta::rating(3.0));
        self::assertSame(['amount' => 3980.0, 'currency' => 'JPY', 'label' => '通常版'], MovieMeta::price(3980.0, 'JPY', ' 通常版 '));
        self::assertSame(['external_id' => '12', 'name' => 'Aoi Mei', 'url' => 'https://example.test/12'], MovieMeta::performer(' Aoi Mei ', '12', 'https://example.test/12'));

        $magnet = MovieMeta::magnet('magnet:?xt=urn:btih:abc', ' SONE-763 ', '8.14GB', '2025-06-20', ['HD', ' ', 'Subtitle']);
        self::assertSame('SONE-763', $magnet['name']);
        self::assertSame('8.14GB', $magnet['size_label']);
        self::assertSame((int) round(8.14 * 1024 ** 3), $magnet['size_bytes']);
        self::assertSame(['HD', 'Subtitle'], $magnet['tags']);
        self::assertNull(MovieMeta::magnet('magnet:?xt=urn:btih:abc')['name']);
    }

    public function test_filter_drops_missing_values_but_keeps_zero_and_false(): void
    {
        self::assertSame(
            ['a' => 0, 'b' => false, 'c' => 'x'],
            MovieMeta::filter(['a' => 0, 'b' => false, 'c' => 'x', 'd' => null, 'e' => '', 'f' => []]),
        );
    }
}
