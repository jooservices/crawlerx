<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\OneFourOneJav\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class OneFourOneJavListingTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Date: 2026/06/04  (pages 1–3, page 3 is last)
    // -----------------------------------------------------------------------

    public function test_date_20260604_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('141jav/date-20260604/page-1.html');
        $url = 'https://www.141jav.com/date/2026/06/04?page=1';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertGreaterThan(0, count($result->items));
        self::assertContainsOnlyInstancesOf(CrawlItemResultDto::class, $result->items);
        foreach ($result->items as $item) {
            self::assertMatchesRegularExpression('#^https://(?:www\.)?141jav\.com/torrent/[^/]+$#i', $item->url);
        }
    }

    public function test_date_20260604_page1_has_next_page(): void
    {
        $client = $this->clientWithFixture('141jav/date-20260604/page-1.html');
        $url = 'https://www.141jav.com/date/2026/06/04?page=1';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertNotNull($result->pagination->nextPage);
    }

    public function test_date_20260604_page2_has_next_page(): void
    {
        $client = $this->clientWithFixture('141jav/date-20260604/page-2.html');
        $url = 'https://www.141jav.com/date/2026/06/04?page=2';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 2));

        self::assertSame(2, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_date_20260604_page3_is_last_page(): void
    {
        $client = $this->clientWithFixture('141jav/date-20260604/page-3.html');
        $url = 'https://www.141jav.com/date/2026/06/04?page=3';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 3));

        self::assertSame(3, $result->pagination->currentPage);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextPage);
        self::assertSame(3, $result->pagination->lastPage);
        self::assertGreaterThan(0, count($result->items));
    }

    // -----------------------------------------------------------------------
    // Date: 2026/06/01  (pages 1–2, page 2 is last)
    // -----------------------------------------------------------------------

    public function test_date_20260601_page1_has_next_page(): void
    {
        $client = $this->clientWithFixture('141jav/date-20260601/page-1.html');
        $url = 'https://www.141jav.com/date/2026/06/01?page=1';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public function test_date_20260601_page2_is_last_page(): void
    {
        $client = $this->clientWithFixture('141jav/date-20260601/page-2.html');
        $url = 'https://www.141jav.com/date/2026/06/01?page=2';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 2));

        self::assertSame(2, $result->pagination->currentPage);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextPage);
        self::assertSame(2, $result->pagination->lastPage);
        self::assertGreaterThan(0, count($result->items));
    }

    // -----------------------------------------------------------------------
    // /new  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_new_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('141jav/new/page-1.html');
        $url = 'https://www.141jav.com/new?page=1';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertGreaterThan(0, count($result->items));
        self::assertContainsOnlyInstancesOf(CrawlItemResultDto::class, $result->items);
        foreach ($result->items as $item) {
            self::assertStringContainsString('141jav.com/torrent/', $item->url);
        }
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('newPagesProvider')]
    public function test_new_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("141jav/new/page-{$page}.html");
        $url = "https://www.141jav.com/new?page={$page}";

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function newPagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 10));
    }

    // -----------------------------------------------------------------------
    // /popular  (pages 1–10)
    // Note: /popular page 1 renders a single pagination link with no inverted
    // pages, so parseBulmaPagination returns hasNextPage=false for page 1.
    // From page 2 onwards the full pagination is visible.
    // -----------------------------------------------------------------------

    public function test_popular_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('141jav/popular/page-1.html');
        $url = 'https://www.141jav.com/popular/?page=1';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
    }

    public function test_popular_page2_has_next_page(): void
    {
        $client = $this->clientWithFixture('141jav/popular/page-2.html');
        $url = 'https://www.141jav.com/popular/?page=2';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 2));

        self::assertSame(2, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertGreaterThan(0, count($result->items));
    }

    #[DataProvider('popularPagesProvider')]
    public function test_popular_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("141jav/popular/page-{$page}.html");
        $url = "https://www.141jav.com/popular/?page={$page}";

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotNull($result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function popularPagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 10));
    }

    // -----------------------------------------------------------------------
    // /random  (pages 1–10)
    // Note: /random renders without pagination links.
    // -----------------------------------------------------------------------

    public function test_random_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('141jav/random/page-1.html');
        $url = 'https://www.141jav.com/random/?page=1';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertNotNull($result->pagination);
    }

    #[DataProvider('randomPagesProvider')]
    public function test_random_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("141jav/random/page-{$page}.html");
        $url = "https://www.141jav.com/random/?page={$page}";

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function randomPagesProvider(): array
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

        $request = new CrawlRequestDto(url: 'https://www.141jav.com/new', type: CrawlType::Listing);
        (new Listing($client))->execute($request);
    }
}
