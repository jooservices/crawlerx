<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\TagActressListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class TagActressListingTest extends TestCase
{
    public function test_tag_index_parses_unique_names_and_request_url_on_items(): void
    {
        $url = 'https://onejav.com/tag/FC2';
        $client = $this->clientWithFixture('onejav/tag-fc2/page-1.html');

        $result = (new TagActressListing($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing, page: 1));

        self::assertContains('FC2', array_map(fn($item) => $item->meta['movie']['external_id'], $result->items));
        foreach ($result->items as $item) {
            self::assertSame($url, $item->url);
        }
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertNull($result->pagination->lastPage);
        self::assertSame(1, $result->pagination->currentPage);
    }
}
