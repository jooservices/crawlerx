<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\FfJav\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * ffjav uses WordPress-style /page/N/ pagination:
 *   Page 1: https://ffjav.com/{section}
 *   Page N: https://ffjav.com/{section}/page/{N}
 */
final class FfJavListingTest extends TestCase
{
    private function pageUrl(string $base, int $page): string
    {
        return $page === 1 ? $base : "{$base}/page/{$page}";
    }

    // -----------------------------------------------------------------------
    // /javtorrent  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_javtorrent_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('ffjav/javtorrent/page-1.html');
        $url = 'https://ffjav.com/javtorrent';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertGreaterThan(0, count($result->items));
        self::assertContainsOnlyInstancesOf(CrawlItemResultDto::class, $result->items);
        foreach ($result->items as $item) {
            self::assertMatchesRegularExpression('#^https://ffjav\.com/torrent/[^/]+$#i', $item->url);
        }
    }

    public function test_javtorrent_page1_has_next_page(): void
    {
        $client = $this->clientWithFixture('ffjav/javtorrent/page-1.html');
        $url = 'https://ffjav.com/javtorrent';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertNotNull($result->pagination->nextPage);
        self::assertStringContainsString('ffjav.com/javtorrent/page/2', (string) $result->pagination->nextUrl);
    }

    public function test_javtorrent_page2_current_page(): void
    {
        $client = $this->clientWithFixture('ffjav/javtorrent/page-2.html');
        $url = 'https://ffjav.com/javtorrent/page/2';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 2));

        self::assertSame(2, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('javtorrentPagesProvider')]
    public function test_javtorrent_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("ffjav/javtorrent/page-{$page}.html");
        $url = $this->pageUrl('https://ffjav.com/javtorrent', $page);

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function javtorrentPagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 10));
    }

    // -----------------------------------------------------------------------
    // /popular  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_popular_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('ffjav/popular/page-1.html');
        $url = 'https://ffjav.com/popular';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('popularPagesProvider')]
    public function test_popular_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("ffjav/popular/page-{$page}.html");
        $url = $this->pageUrl('https://ffjav.com/popular', $page);

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function popularPagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 10));
    }

    // -----------------------------------------------------------------------
    // /category/amateur  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_category_amateur_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('ffjav/category-amateur/page-1.html');
        $url = 'https://ffjav.com/category/amateur';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('categoryAmateurPagesProvider')]
    public function test_category_amateur_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("ffjav/category-amateur/page-{$page}.html");
        $url = $this->pageUrl('https://ffjav.com/category/amateur', $page);

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function categoryAmateurPagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 10));
    }

    // -----------------------------------------------------------------------
    // /category/jav-censored  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_category_jav_censored_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('ffjav/category-jav-censored/page-1.html');
        $url = 'https://ffjav.com/category/jav-censored';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('categoryJavCensoredPagesProvider')]
    public function test_category_jav_censored_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("ffjav/category-jav-censored/page-{$page}.html");
        $url = $this->pageUrl('https://ffjav.com/category/jav-censored', $page);

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function categoryJavCensoredPagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 10));
    }

    // -----------------------------------------------------------------------
    // /category/jav-uncensored  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_category_jav_uncensored_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('ffjav/category-jav-uncensored/page-1.html');
        $url = 'https://ffjav.com/category/jav-uncensored';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('categoryJavUncensoredPagesProvider')]
    public function test_category_jav_uncensored_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("ffjav/category-jav-uncensored/page-{$page}.html");
        $url = $this->pageUrl('https://ffjav.com/category/jav-uncensored', $page);

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function categoryJavUncensoredPagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 10));
    }

    // -----------------------------------------------------------------------
    // Error handling
    // -----------------------------------------------------------------------

    public function test_listing_throws_on_empty_html(): void
    {
        $client = $this->clientWithHtml('');

        $this->expectException(RuntimeException::class);

        $request = new CrawlRequestDto(url: 'https://ffjav.com/javtorrent', type: CrawlType::Listing);
        (new Listing($client))->execute($request);
    }
}
