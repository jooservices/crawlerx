<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Tma\ProductUrl;
use PHPUnit\Framework\TestCase;

final class ProductUrlTest extends TestCase
{
    public function test_slug_is_read_from_both_product_url_shapes(): void
    {
        self::assertSame('t38-073', ProductUrl::slug('https://www.tma.co.jp/products/t38-073'));
        self::assertSame('t38-073', ProductUrl::slug('https://www.tma.co.jp/collections/tma/products/t38-073/'));
        self::assertNull(ProductUrl::slug('https://www.tma.co.jp/collections/tma'));
        self::assertNull(ProductUrl::slug('https://www.tma.co.jp/products/'));
    }

    public function test_code_is_upper_case_for_code_slugs_and_null_for_japanese_slugs(): void
    {
        self::assertSame('34ID-033', ProductUrl::code('34id-033'));
        self::assertSame('T38-073', ProductUrl::code('t38-073'));
        self::assertNull(ProductUrl::code(rawurlencode('壁尻個室アナル風俗')));
    }

    public function test_external_id_is_the_lower_case_decoded_slug(): void
    {
        self::assertSame('fays-017', ProductUrl::externalId('FAYS-017'));
        self::assertSame('壁尻個室アナル風俗', ProductUrl::externalId(rawurlencode('壁尻個室アナル風俗')));
    }
}
