<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Javbtc;

use JOOservices\CrawlerX\Adapters\Javbtc\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class ListingTest extends TestCase
{
    private const URL = 'https://javbtc.com/r18/';

    public function test_cards_become_movie_items_with_cover_title_and_duration(): void
    {
        $html = $this->page(
            '<div class="a4"><a href="/r18/abc-123"><img src="/c/1.jpg" alt=" ABC 123 title "><b>x</b><b>12:34</b></a></div>'
            . '<div class="a4"><a href="/r18/def-456">DEF 456 text 1:05</a></div>'
            . '<div class="a4"><a href="/r18/ghi-789"><img src="" alt="GHI"><b>no duration</b></a></div>',
        );

        $result = $this->execute($html);

        self::assertCount(3, $result->items);
        self::assertSame('https://javbtc.com/r18/abc-123', $result->items[0]->url);
        self::assertSame('abc-123', $result->items[0]->meta['movie']['external_id']);
        self::assertSame('ABC 123 title', $result->items[0]->meta['movie']['title']);
        self::assertSame('https://javbtc.com/c/1.jpg', $result->items[0]->meta['movie']['cover_url']);
        self::assertSame('12:34', $result->items[0]->meta['movie']['metadata']['clip_duration_label']);
        self::assertSame('1:05', $result->items[1]->meta['movie']['metadata']['clip_duration_label']);
        self::assertEmpty($result->items[2]->meta['movie']['metadata'] ?? []);
        self::assertNull($result->items[2]->meta['movie']['cover_url'] ?? null);
    }

    public function test_numeric_page_links_blank_hrefs_and_foreign_urls_are_not_movie_cards(): void
    {
        $html = $this->page(
            '<div class="a4"><a href="/r18/2">page two</a></div>'
            . '<div class="a4"><a href=" ">blank</a></div>'
            . '<div class="a4"><a href="/r18/sub/dir">nested</a></div>'
            . '<div class="a4"><a href="/r18/">root</a></div>'
            . '<div class="a4"><a href="/r18/real-1">Real</a></div>',
        );

        $result = $this->execute($html);

        self::assertSame(['https://javbtc.com/r18/real-1'], array_map(static fn($i) => $i->url, $result->items));
    }

    public function test_a_listing_without_cards_is_a_parse_failure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('did not contain expected movie fields');

        $this->execute('<html><body><div class="a3a"></div></body></html>');
    }

    public function test_pagination_uses_the_nearest_greater_page_and_the_highest_visible_page(): void
    {
        $html = $this->page(
            $this->card(),
            '<div class="a3a"><b>2</b><a href="/r18/1">1</a><a href="/r18/3">3</a><a href="/r18/4">4</a></div>',
        );

        $pagination = $this->execute($html)->pagination;

        self::assertSame(2, $pagination->currentPage);
        self::assertSame(3, $pagination->nextPage);
        self::assertSame('https://javbtc.com/r18/3', $pagination->nextUrl);
        self::assertSame(4, $pagination->lastPage);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_the_last_page_comes_from_the_double_arrow_link(): void
    {
        $html = $this->page(
            $this->card(),
            '<div class="a3a"><b>1</b><a href="/r18/2">2</a><a href="/r18/50">»</a></div>',
        );

        $pagination = $this->execute($html)->pagination;

        self::assertSame(50, $pagination->lastPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_a_non_numeric_last_page_link_falls_back_to_the_highest_visible_page(): void
    {
        $html = $this->page(
            $this->card(),
            '<div class="a3a"><b>1</b><a href="/r18/2">2</a><a href="/r18/latest"><i class="fa-arrow-alt-circle-right"></i></a></div>',
        );

        $pagination = $this->execute($html)->pagination;

        self::assertSame(2, $pagination->lastPage);
        self::assertSame(2, $pagination->nextPage);
    }

    public function test_an_explicit_next_arrow_is_used_when_no_numbered_page_follows(): void
    {
        $html = $this->page(
            $this->card(),
            '<div class="a3a"><b>3</b><a href="/r18/more">›</a></div>',
        );

        $pagination = $this->execute($html)->pagination;

        self::assertSame('https://javbtc.com/r18/more', $pagination->nextUrl);
        self::assertSame(4, $pagination->nextPage);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_a_next_label_in_any_case_is_an_explicit_next_link(): void
    {
        $html = $this->page(
            $this->card(),
            '<div class="a9"><b>1</b><a href="/r18/9">NEXT</a></div>',
        );

        $pagination = $this->execute($html)->pagination;

        self::assertSame(9, $pagination->nextPage);
    }

    public function test_the_last_page_has_no_next_page(): void
    {
        $html = $this->page(
            $this->card(),
            '<div class="a3a"><a href="/r18/1">1</a><a href="/r18/2">2</a><b>3</b></div>',
        );

        $pagination = $this->execute($html)->pagination;

        self::assertSame(3, $pagination->currentPage);
        self::assertFalse($pagination->hasNextPage);
        self::assertNull($pagination->nextPage);
    }

    public function test_a_request_page_below_one_is_treated_as_the_first_page(): void
    {
        $html = $this->page($this->card(), '<div class="a3a"><a href="/r18/2">2</a></div>');

        $result = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Listing, page: 0));

        self::assertSame(1, $result->pagination->currentPage);
    }

    private function card(): string
    {
        return '<div class="a4"><a href="/r18/abc-123">ABC</a></div>';
    }

    private function page(string $cards, string $pagination = ''): string
    {
        return '<html><body>' . $cards . $pagination . '</body></html>';
    }

    private function execute(string $html): CrawlListResultDto
    {
        return (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Listing));
    }
}
