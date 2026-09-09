<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Support\QueryPageUrl;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class QueryPageUrlTest extends TestCase
{
    #[DataProvider('pageUrlProvider')]
    public function test_build_matches_onejav_page_url_behavior(string $url, int $page, string $expected): void
    {
        self::assertSame($expected, QueryPageUrl::build($url, $page));
    }

    public static function pageUrlProvider(): array
    {
        return [
            ['https://onejav.com/new', 1, 'https://onejav.com/new'],
            ['https://onejav.com/new?page=3', 1, 'https://onejav.com/new'],
            ['https://onejav.com/new?page=2', 4, 'https://onejav.com/new?page=4'],
            ['https://onejav.com/new', 2, 'https://onejav.com/new?page=2'],
        ];
    }
}
