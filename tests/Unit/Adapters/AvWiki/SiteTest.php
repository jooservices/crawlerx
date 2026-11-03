<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvWiki;

use JOOservices\CrawlerX\Adapters\AvWiki\Site;
use PHPUnit\Framework\TestCase;

final class SiteTest extends TestCase
{
    public function test_builds_listing_api_and_pagination_urls(): void
    {
        self::assertSame(
            'https://av-wiki.net/wp-json/wp/v2/posts?per_page=100&page=3&_embed=1',
            Site::listingApiUrl('https://av-wiki.net/', 3),
        );
        self::assertSame(
            'https://av-wiki.net/wp-json/wp/v2/posts?per_page=2&page=4&_embed=1',
            Site::listingPageUrl('https://av-wiki.net/wp-json/wp/v2/posts?per_page=2&page=1&_embed=1', 4),
        );
        self::assertSame(2, Site::pageSize('https://av-wiki.net/wp-json/wp/v2/posts?per_page=2'));
        self::assertSame(100, Site::pageSize('https://av-wiki.net/'));
    }

    public function test_builds_detail_api_urls(): void
    {
        self::assertSame(
            'https://av-wiki.net/wp-json/wp/v2/posts?slug=200gana-3482&_embed=1',
            Site::detailApiUrl('https://av-wiki.net/200gana-3482/'),
        );
        self::assertSame(
            'https://av-wiki.net/wp-json/wp/v2/posts/123?_embed=1',
            Site::detailApiUrl('https://av-wiki.net/wp-json/wp/v2/posts/123'),
        );
    }

    public function test_resolves_absolute_and_relative_links(): void
    {
        self::assertSame('https://av-wiki.net/path', Site::absoluteUrl('/path'));
        self::assertSame('https://cdn.example/image.jpg', Site::absoluteUrl('//cdn.example/image.jpg'));
        self::assertSame('https://av-wiki.net/image.jpg', Site::absoluteUrl('image.jpg'));
        self::assertSame('https://cdn.example/image.jpg', Site::absoluteUrl('https://cdn.example/image.jpg'));
    }
}
