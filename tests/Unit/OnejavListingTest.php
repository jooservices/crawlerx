<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Onejav\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class OnejavListingTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Date: 2026/06/05  (pages 1–7)
    // -----------------------------------------------------------------------

    public function test_date_20260605_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('onejav/date-20260605/page-1.html');
        $url = 'https://onejav.com/2026/06/05';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertGreaterThan(0, count($result->items));
        self::assertContainsOnlyInstancesOf(CrawlItemResultDto::class, $result->items);
        foreach ($result->items as $item) {
            self::assertMatchesRegularExpression('#^https://onejav\.com/torrent/[^/]+$#i', $item->url);
        }
    }

    public function test_date_20260605_page1_has_next_page(): void
    {
        $client = $this->clientWithFixture('onejav/date-20260605/page-1.html');
        $url = 'https://onejav.com/2026/06/05';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertNotNull($result->pagination->nextPage);
        self::assertNotNull($result->pagination->nextUrl);
    }

    public function test_date_20260605_page6_has_next_page(): void
    {
        $client = $this->clientWithFixture('onejav/date-20260605/page-6.html');
        $url = 'https://onejav.com/2026/06/05?page=6';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 6));

        self::assertSame(6, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_date_20260605_page7_is_last_page(): void
    {
        $client = $this->clientWithFixture('onejav/date-20260605/page-7.html');
        $url = 'https://onejav.com/2026/06/05?page=7';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 7));

        self::assertSame(7, $result->pagination->currentPage);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextPage);
        self::assertSame(7, $result->pagination->lastPage);
    }

    #[DataProvider('date20260605PagesProvider')]
    public function test_date_20260605_pages_parse_correctly(int $page): void
    {
        $client = $this->clientWithFixture("onejav/date-20260605/page-{$page}.html");
        $url = $page === 1 ? 'https://onejav.com/2026/06/05' : "https://onejav.com/2026/06/05?page={$page}";

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function date20260605PagesProvider(): array
    {
        return array_map(fn(int $p): array => [$p], range(1, 7));
    }

    // -----------------------------------------------------------------------
    // /new  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_new_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('onejav/new/page-1.html');
        $url = 'https://onejav.com/new';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertContainsOnlyInstancesOf(CrawlItemResultDto::class, $result->items);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertNotNull($result->pagination->nextUrl);
    }

    #[DataProvider('newPagesProvider')]
    public function test_new_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("onejav/new/page-{$page}.html");
        $url = "https://onejav.com/new?page={$page}";

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
    // -----------------------------------------------------------------------

    public function test_popular_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('onejav/popular/page-1.html');
        $url = 'https://onejav.com/popular/';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('popularPagesProvider')]
    public function test_popular_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("onejav/popular/page-{$page}.html");
        $url = "https://onejav.com/popular/?page={$page}";

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function popularPagesProvider(): array
    {
        // onejav popular has 5 accessible pages; pages 6+ return an empty listing
        return array_map(fn(int $p): array => [$p], range(1, 5));
    }

    // -----------------------------------------------------------------------
    // /tag/FC2  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_tag_fc2_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('onejav/tag-fc2/page-1.html');
        $url = 'https://onejav.com/tag/FC2';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('tagFc2PagesProvider')]
    public function test_tag_fc2_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("onejav/tag-fc2/page-{$page}.html");
        $url = "https://onejav.com/tag/FC2?page={$page}";

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function tagFc2PagesProvider(): array
    {
        // Page 3 of FC2 is blocked by Cloudflare; test all other pages
        $pages = array_filter(range(1, 10), fn(int $p): bool => $p !== 3);

        return array_map(fn(int $p): array => [$p], array_values($pages));
    }

    // -----------------------------------------------------------------------
    // /tag/JavPlayer  (pages 1–10)
    // -----------------------------------------------------------------------

    public function test_tag_javplayer_page1_parses_items(): void
    {
        $client = $this->clientWithFixture('onejav/tag-javplayer/page-1.html');
        $url = 'https://onejav.com/tag/JavPlayer';

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(0, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
    }

    #[DataProvider('tagJavPlayerPagesProvider')]
    public function test_tag_javplayer_pages_parse_without_error(int $page): void
    {
        $client = $this->clientWithFixture("onejav/tag-javplayer/page-{$page}.html");
        $url = "https://onejav.com/tag/JavPlayer?page={$page}";

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: $page));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame($page, $result->pagination->currentPage);
        self::assertGreaterThan(0, count($result->items));
    }

    public static function tagJavPlayerPagesProvider(): array
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

        $request = new CrawlRequestDto(url: 'https://onejav.com/2026/06/05', type: CrawlType::Listing);
        (new Listing($client))->execute($request);
    }
}
