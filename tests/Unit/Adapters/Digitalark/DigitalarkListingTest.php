<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Digitalark;

use JOOservices\CrawlerX\Adapters\Digitalark\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class DigitalarkListingTest extends TestCase
{
    private const BASE = 'https://shop.digital-ark.com';

    private const RELEASE_LIST = self::BASE . '/view/category/release-date';

    public function test_listing_page_one_returns_all_fifty_cards(): void
    {
        $result = $this->listing('digitalark/listing-page-1.html', self::RELEASE_LIST, 1);

        self::assertSame('movie', $result->entityType);
        self::assertCount(50, $result->items);
    }

    public function test_first_card_exposes_code_title_cover_and_detail_url(): void
    {
        $result = $this->listing('digitalark/listing-page-1.html', self::RELEASE_LIST, 1);
        $movie = $result->items[0]->meta['movie'];

        self::assertSame(self::BASE . '/view/item/000000002262', $result->items[0]->url);
        self::assertSame('000000002262', $movie['external_id']);
        self::assertSame('000000002262', $movie['code']);
        self::assertSame('チ◯ポを励ます応援勃起チャージガール　ムッチリ肉感デカ尻チアガール', $movie['title']);
        self::assertSame('https://makeshop-multi-images.akamaized.net/degea/itemimages/000000002262_5hPeesG.jpg', $movie['cover_url']);
        self::assertSame(['maker' => 'DIGITAL ARK'], $movie['metadata']);
    }

    public function test_page_one_reports_the_shop_pager_with_next_page_and_last_page(): void
    {
        $pagination = $this->listing('digitalark/listing-page-1.html', self::RELEASE_LIST, 1)->pagination;

        self::assertSame(1, $pagination->currentPage);
        self::assertSame(40, $pagination->lastPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertSame(self::RELEASE_LIST . '?page=2', $pagination->nextUrl);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_page_two_uses_its_own_active_page_and_next_link(): void
    {
        $result = $this->listing('digitalark/listing-page-2.html', self::RELEASE_LIST . '?page=2', 2);

        self::assertSame(2, $result->page);
        self::assertCount(50, $result->items);
        self::assertSame('000000002215', $result->items[0]->meta['movie']['external_id']);
        self::assertSame(self::BASE . '/view/item/000000002215', $result->items[0]->url);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(40, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::RELEASE_LIST . '?page=3', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_listing_rejects_a_page_without_product_cards(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><main>nothing</main></body></html>')))->execute(new CrawlRequestDto(
            url: self::RELEASE_LIST,
        ));
    }

    private function listing(string $fixture, string $url, int $page): CrawlListResultDto
    {
        return (new Listing($this->clientWithFixture($fixture)))->execute(new CrawlRequestDto(url: $url, page: $page));
    }
}
