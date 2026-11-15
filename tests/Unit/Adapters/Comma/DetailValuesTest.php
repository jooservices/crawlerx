<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Comma;

use JOOservices\CrawlerX\Adapters\Comma\DetailValues;
use PHPUnit\Framework\TestCase;

final class DetailValuesTest extends TestCase
{
    public function test_performers_are_split_on_the_full_width_slash_and_empty_names_dropped(): void
    {
        self::assertSame(['A・B', 'C'], DetailValues::performers('A・B／C ／ '));
        self::assertSame([], DetailValues::performers(null));
        self::assertSame([], DetailValues::performers('／'));
    }

    public function test_release_date_takes_the_day_printed_after_the_year_month_link(): void
    {
        self::assertSame('2023-07-06', DetailValues::releaseDate('2023年7月07月06日'));
        self::assertSame('2023-07-06', DetailValues::releaseDate('2023年7月6日'));
        self::assertNull(DetailValues::releaseDate('2023年7月'));
        self::assertNull(DetailValues::releaseDate('2023年2月30日'));
        self::assertNull(DetailValues::releaseDate(null));
    }

    public function test_price_uses_the_tax_included_amount_when_printed(): void
    {
        self::assertSame(
            ['amount' => 4950.0, 'currency' => 'JPY', 'label' => '¥ 4,500- （税込 ¥4,950-）'],
            DetailValues::price('¥ 4,500- （税込 ¥4,950-）'),
        );
        self::assertSame(['amount' => 1200.0, 'currency' => 'JPY', 'label' => '¥1,200-'], DetailValues::price('¥1,200-'));
        self::assertNull(DetailValues::price('無料'));
        self::assertNull(DetailValues::price(null));
    }

    public function test_comment_splits_the_synopsis_from_the_original_title(): void
    {
        $comment = DetailValues::comment('セールス・ポイント／解説 本編 原題 : PUPPET SHARK © 2022 SRS Cinema.');

        self::assertSame('セールス・ポイント／解説 本編', $comment['description']);
        self::assertSame('PUPPET SHARK', $comment['original_title']);
    }

    public function test_comment_without_an_original_title_keeps_the_whole_text(): void
    {
        $comment = DetailValues::comment('あらすじ 本編');

        self::assertSame('あらすじ 本編', $comment['description']);
        self::assertNull($comment['original_title']);
        self::assertSame(['description' => null, 'original_title' => null], DetailValues::comment(null));
    }
}
