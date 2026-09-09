<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Support\CodeNormalizer;
use JOOservices\CrawlerX\Support\SizeParser;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SupportNormalizerTest extends TestCase
{
    #[DataProvider('canonicalCodeProvider')]
    public function test_canonical_code_extracts_expected_value(?string $expected, ?string ...$values): void
    {
        self::assertSame($expected, CodeNormalizer::canonical(...$values));
    }

    /**
     * @return array<string, array{0: string|null, 1?: string|null, 2?: string|null, 3?: string|null}>
     */
    public static function canonicalCodeProvider(): array
    {
        return [
            'standard code' => ['YMDS-282', 'YMDS282'],
            'hyphenated code' => ['MIDA-549', 'MIDA-549 エロ好き素人女子大生'],
            'fc2 code' => ['FC2-PPV-4909288', 'fc2ppv4909288'],
            'fallback to later value' => ['SMOK-039', null, 'not a code', 'https://www.141jav.com/torrent/SMOK039'],
            'no code' => [null, null, 'sample'],
        ];
    }

    #[DataProvider('sizeBytesProvider')]
    public function test_parse_size_bytes_extracts_expected_value(?int $expected, ?string $text): void
    {
        self::assertSame($expected, SizeParser::bytes($text));
    }

    /**
     * @return array<string, array{0: int|null, 1: string|null}>
     */
    public static function sizeBytesProvider(): array
    {
        return [
            'gb' => [8053063680, '7.5 GB'],
            'decimal gb' => [4402341478, '4.1 GB'],
            'nbsp gb' => [8053063680, "7.5\xc2\xa0GB"],
            'comma gib' => [1288490189, '1,2 GiB'],
            'mb' => [734003200, '700 MB'],
            'empty' => [null, ''],
            'not size' => [null, 'not a size'],
        ];
    }
}
