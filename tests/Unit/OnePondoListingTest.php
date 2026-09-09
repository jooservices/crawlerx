<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\OnePondo\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class OnePondoListingTest extends TestCase
{
    public function test_listing_parses_newest_page_one(): void
    {
        $json = $this->loadFixture('onepondo/list-newest-0.json');
        $client = $this->clientWithHtml($json);

        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.1pondo.tv/list/?o=n',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertGreaterThan(0, count($result->items));
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertStringContainsString('page=2', (string) $result->pagination->nextUrl);

        $first = $result->items[0];
        self::assertInstanceOf(CrawlItemResultDto::class, $first);
        self::assertMatchesRegularExpression('/^\d{6}_\d{3}$/', (string) $first->meta['movie']['external_id']);
        self::assertStringContainsString('/movies/', $first->url);
    }

    public function test_listing_parses_page_two_with_offset_chunk(): void
    {
        $pageOne = $this->loadFixture('onepondo/list-newest-0.json');
        $pageTwo = $this->loadFixture('onepondo/list-newest-50.json');

        $client = $this->clientWithMappedResponses([
            'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json' => ['body' => $pageOne],
            'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_50.json' => ['body' => $pageTwo],
        ]);

        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.1pondo.tv/list/?o=n',
            type: CrawlType::Listing,
            page: 2,
        ));

        self::assertSame(2, $result->page);
        self::assertGreaterThan(0, count($result->items));
        self::assertSame('031926_001', $result->items[0]->meta['movie']['external_id']);
    }

    public function test_listing_defaults_unknown_order_to_newest(): void
    {
        $json = $this->loadFixture('onepondo/list-newest-0.json');
        $client = $this->clientWithHtml($json);

        (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.1pondo.tv/list/?o=unknown',
            type: CrawlType::Listing,
        ));
    }

    public function test_listing_throws_on_empty_rows(): void
    {
        $client = $this->clientWithHtml('{"TotalRows":0,"SplitSize":50,"Rows":[]}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OnePondo listing response did not contain expected movie fields.');

        (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://en.1pondo.tv/list/?o=n',
            type: CrawlType::Listing,
        ));
    }
}
