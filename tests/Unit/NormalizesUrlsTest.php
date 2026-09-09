<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NormalizesUrlsTest extends TestCase
{
    use NormalizesUrls;

    #[DataProvider('absoluteUrlProvider')]
    public function test_absolute_resolves_relative_and_protocol_relative_urls(string $baseUrl, string $relative, string $expected): void
    {
        self::assertSame($expected, $this->absolute($baseUrl, $relative));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function absoluteUrlProvider(): array
    {
        return [
            'absolute https unchanged' => ['https://example.com/a', 'https://cdn.example/img.jpg', 'https://cdn.example/img.jpg'],
            'path relative joins to base directory' => ['https://example.com/a/b', 'c/d.jpg', 'https://example.com/a/c/d.jpg'],
            'slash prefixed path joins to origin host' => ['https://example.com/a/b', '/c/d.jpg', 'https://example.com/c/d.jpg'],
            'query on base path' => ['https://example.com/list', '?page=2', 'https://example.com/list?page=2'],
            'protocol relative inherits https' => ['https://example.com/a', '//cdn.example/img.jpg', 'https://cdn.example/img.jpg'],
            'protocol relative inherits http' => ['http://example.com/a', '//cdn.example/img.jpg', 'http://cdn.example/img.jpg'],
            'protocol relative defaults to https' => ['example.com/a', '//cdn.example/img.jpg', 'https://cdn.example/img.jpg'],
        ];
    }
}
