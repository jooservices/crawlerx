<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Javbtc\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class JavbtcListingTest extends TestCase
{
    public function test_listing_parses_main_feed_from_fixture(): void
    {
        $url = 'https://javbtc.com/';
        $client = $this->clientWithFixture('javbtc/listing-page-1.html');

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertGreaterThan(20, count($result->items));

        $matchedUrl = null;
        foreach ($result->items as $item) {
            if ($item->meta['movie']['external_id'] === 's1no1style-yua-mikami-sougouwiki-celebrity') {
                $matchedUrl = $item->url;
                break;
            }
        }
        self::assertSame(
            'https://javbtc.com/r18/s1no1style-yua-mikami-sougouwiki-celebrity',
            $matchedUrl,
        );
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame('https://javbtc.com/r18/2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_listing_parses_performer_feed_with_pagination(): void
    {
        $url = 'https://javbtc.com/r18/yua-mikami';
        $client = $this->clientWithFixture('javbtc/listing-performer.html');

        $result = (new Listing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertGreaterThan(10, count($result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame('https://javbtc.com/r18/yua-mikami/2', $result->pagination->nextUrl);
    }

    public function test_listing_throws_on_empty_html(): void
    {
        $client = $this->clientWithHtml('');

        $this->expectException(RuntimeException::class);

        (new Listing($client))->execute(new CrawlRequestDto(url: 'https://javbtc.com/', type: CrawlType::Listing));
    }
}
