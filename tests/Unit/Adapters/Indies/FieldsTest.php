<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Indies;

use JOOservices\CrawlerX\Adapters\Indies\Fields;
use JOOservices\CrawlerX\Tests\TestCase;
use Symfony\Component\DomCrawler\Crawler;

final class FieldsTest extends TestCase
{
    public function test_slug_takes_the_segment_after_the_given_one(): void
    {
        self::assertSame('dsdp049', Fields::slug('https://www.indies-av.co.jp/title/dsdp049/', 'title'));
        self::assertSame('尻フェチ', Fields::slug('https://www.indies-av.co.jp/genre/%e5%b0%bb%e3%83%95%e3%82%a7%e3%83%81', 'genre'));
        self::assertNull(Fields::slug('https://www.indies-av.co.jp/actpage/', 'title'));
        self::assertNull(Fields::slug('not a url', 'title'));
    }

    public function test_visible_text_skips_display_none_helpers(): void
    {
        $cell = (new Crawler('<div><span><span style=display:none>レーベル</span><a>SWEET</a> DEVIL</span></div>'))->filter('span')->first();

        self::assertSame('SWEET DEVIL', Fields::visibleText($cell));
        self::assertNull(Fields::clean("  \n "));
    }

    public function test_meaningful_rejects_the_empty_profile_templates(): void
    {
        self::assertNull(Fields::meaningful(null));
        self::assertNull(Fields::meaningful('cm'));
        self::assertNull(Fields::meaningful('B:cm(カップ) / W: cm / H:cm'));
        self::assertNull(Fields::meaningful('／'));
        self::assertSame('143cm', Fields::meaningful('143cm'));
        self::assertSame('歌／ギター', Fields::meaningful('歌／ギター'));
    }

    public function test_prices_split_list_and_tax_included_amounts(): void
    {
        $prices = Fields::prices('¥3,180 （税込 ¥3,498）');
        self::assertSame(3180.0, $prices['price']['amount'] ?? null);
        self::assertSame(3498.0, $prices['price_with_tax']['amount'] ?? null);
        self::assertSame(['price' => null, 'price_with_tax' => null], Fields::prices('オープン価格'));
        self::assertSame(['price' => null, 'price_with_tax' => null], Fields::prices(null));
    }

    public function test_page_number_reads_the_path_segment(): void
    {
        self::assertSame(3, Fields::pageNumber('https://www.indies-av.co.jp/genre/anal/page/3/'));
        self::assertNull(Fields::pageNumber('https://www.indies-av.co.jp/genre/anal/'));
    }
}
