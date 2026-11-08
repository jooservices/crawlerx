<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Support;

use JOOservices\CrawlerX\Support\ImageUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageUrlTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function urls(): iterable
    {
        yield 'no query' => ['https://a.test/x.jpg', 'https://a.test/x.jpg'];
        yield 'quality only' => ['https://cdn.test/x.jpg?output-quality=60', 'https://cdn.test/x.jpg'];
        yield 'quality and resize' => ['https://cdn.test/x.jpg?resize=300%2C200&output-quality=60', 'https://cdn.test/x.jpg'];
        yield 'other parameters stay in order' => ['https://cdn.test/x.jpg?v=3&output-quality=60&t=9', 'https://cdn.test/x.jpg?v=3&t=9'];
        yield 'fc2 thumbnail' => [
            'https://contents-thumbnail2.fc2.com/w360/storage201000.contents.fc2.com/file/389/38893931/1788425526.79.jpg',
            'https://storage201000.contents.fc2.com/file/389/38893931/1788425526.79.jpg',
        ];
        yield 'fc2 square thumbnail, protocol-relative' => [
            '//contents-thumbnail2.fc2.com/w262h262/storage202000.contents.fc2.com/file/399/39896198/1787909480.39.png',
            'https://storage202000.contents.fc2.com/file/399/39896198/1787909480.39.png',
        ];
        yield 'fc2 original is unchanged' => [
            'https://storage202000.contents.fc2.com/file/399/39896198/1787909480.39.png',
            'https://storage202000.contents.fc2.com/file/399/39896198/1787909480.39.png',
        ];
    }

    #[DataProvider('urls')]
    public function test_original_drops_the_recompression(string $url, string $expected): void
    {
        self::assertSame($expected, ImageUrl::original($url));
    }
}
