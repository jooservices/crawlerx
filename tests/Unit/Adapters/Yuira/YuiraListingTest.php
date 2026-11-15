<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Yuira;

use JOOservices\CrawlerX\Adapters\Yuira\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class YuiraListingTest extends TestCase
{
    private const BASE = 'https://yuira.jp';

    private const FIRST_PAGE = self::BASE . '/allproduct/?orderby=date';

    public function test_first_page_card_has_detail_url_external_id_title_and_cover(): void
    {
        $result = $this->listing('yuira/listing-page1.html', self::FIRST_PAGE);
        $item = $result->items[0];
        $movie = $item->meta['movie'];

        self::assertCount(25, $result->items);
        self::assertSame(self::BASE . '/product/%e3%81%ab%e3%81%98%e3%82%ab%e3%83%8e%e3%83%9e%e3%82%b8%e3%83%8f%e3%83%a1-hole-%e8%91%89%e9%9a%a0%e5%8d%83%e5%bd%b1/', $item->url);
        self::assertSame('にじカノマジハメ-hole-葉隠千影', $movie['external_id']);
        self::assertSame('にじカノマジハメ HOLE 葉隠千影', $movie['title']);
        self::assertSame(self::BASE . '/wp-content/uploads/2026/09/4571644998269-00-300x300.jpg', $movie['cover_url']);
    }

    public function test_listing_cards_carry_no_product_code_because_the_site_prints_none_on_cards(): void
    {
        $result = $this->listing('yuira/listing-page1.html', self::FIRST_PAGE);

        foreach ($result->items as $item) {
            self::assertNull($item->meta['movie']['code']);
        }
    }

    public function test_first_page_pagination_follows_the_site_page_numbers(): void
    {
        $result = $this->listing('yuira/listing-page1.html', self::FIRST_PAGE);

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(23, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/allproduct/?orderby=date&product-page=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_second_page_is_read_from_its_own_markup(): void
    {
        $result = $this->listing('yuira/listing-page2.html', self::BASE . '/allproduct/?orderby=date&product-page=2');

        self::assertCount(25, $result->items);
        self::assertSame(2, $result->page);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(23, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/allproduct/?orderby=date&product-page=3', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_last_page_without_next_link_reports_no_next_page(): void
    {
        $html = <<<'HTML'
            <ul class="products">
                <li class="product type-product">
                    <ul class="woo-entry-inner"><li class="title"><h2><a href="https://yuira.jp/product/sample-work/">Sample work</a></h2></li></ul>
                </li>
            </ul>
            <nav class="woocommerce-pagination">
                <ul class="page-numbers">
                    <li><a class="page-numbers" href="/allproduct/?orderby=date&product-page=22">22</a></li>
                    <li><span class="page-numbers current">23</span></li>
                </ul>
            </nav>
            HTML;
        $result = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::FIRST_PAGE));

        self::assertSame(23, $result->pagination->currentPage);
        self::assertSame(23, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_cards_without_a_title_link_are_skipped(): void
    {
        $html = <<<'HTML'
            <ul class="products">
                <li class="product type-product"><img data-src="https://yuira.jp/wp-content/uploads/x-300x300.jpg"></li>
                <li class="product type-product">
                    <ul class="woo-entry-inner"><li class="title"><h2><a href="/product/sample-work/">Sample work</a></h2></li></ul>
                </li>
            </ul>
            HTML;
        $result = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::FIRST_PAGE));

        self::assertCount(1, $result->items);
        self::assertSame(self::BASE . '/product/sample-work/', $result->items[0]->url);
        self::assertSame('Sample work', $result->items[0]->meta['movie']['title']);
    }

    private function listing(string $fixture, string $url): \JOOservices\CrawlerX\Dto\CrawlListResultDto
    {
        return (new Listing($this->clientWithFixture($fixture)))
            ->execute(new CrawlRequestDto(url: $url));
    }
}
