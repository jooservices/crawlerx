<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Jable;

use JOOservices\CrawlerX\Adapters\Jable\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class ListingTest extends TestCase
{
    public function test_listing_page_one_parses_items_and_pagination(): void
    {
        $client = $this->clientWithFixture('jable/listing-new-release.html');
        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/new-release/',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertNotEmpty($result->items);
        self::assertMatchesRegularExpression('#^https://en\.jable\.tv/videos/#', $result->items[0]->url);
        self::assertNotSame('', (string) $result->items[0]->meta['movie']['external_id']);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_listing_page_two_advances_pagination(): void
    {
        $client = $this->clientWithFixture('jable/listing-new-release-page2.html');
        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/new-release/2/',
            type: CrawlType::Listing,
            page: 2,
        ));

        self::assertSame('dsod-020', $result->items[0]->meta['movie']['external_id']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(3, $result->pagination->nextPage);
    }

    public function test_listing_last_page_has_no_next_page(): void
    {
        $client = $this->clientWithFixture('jable/listing-new-release-last-page.html');
        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/new-release/1625/',
            type: CrawlType::Listing,
            page: 1625,
        ));

        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextPage);
    }

    public function test_listing_fails_on_cloudflare_challenge(): void
    {
        $client = $this->clientWithHtml('<html>Just a moment...</html>', 403, ['cf-mitigated' => 'challenge']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jable listing page is blocked or unavailable.');

        (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/new-release/',
            type: CrawlType::Listing,
        ));
    }
}
