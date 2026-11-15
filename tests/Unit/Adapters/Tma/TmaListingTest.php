<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Tma\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class TmaListingTest extends TestCase
{
    private const BASE = 'https://www.tma.co.jp';

    private const PAGE_ONE_CODES = [
        'TAIB-035', 'TAIB-034', 'TAIB-033', 'FAYS-019', 'T38-079', 'T38-078', 'T38-077', '34ID-034', '34ID-033',
        null, null, 'TAIB-031', 'TAIB-029', 'FAYS-018', 'T38-076', 'T38-075', '34ID-032', '34ID-031',
        null, null, 'FAYS-017', 'T38-073', 'T38-074', '34ID-030',
    ];

    private const PAGE_TWO_CODES = [
        '34ID-029', '34ID-028', null, null, 'TAIB-028', 'TAIB-027', 'TAIB-026', null, null, 'FAYS-016',
        'T38-072', 'T38-071', 'T38-070', '34ID-027', '34ID-026', '34ID-025', 'TAIB-025', 'TAIB-024', 'TAIB-023',
        'TAIB-021', 'TAIB-022', 'TAIB-020', 'FAYS-015', 'T38-069',
    ];

    public function test_page_one_returns_every_card_with_code_title_cover_and_detail_url(): void
    {
        $result = (new Listing($this->clientWithFixture('tma/listing-page1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/collections/tma'));

        self::assertCount(24, $result->items);
        self::assertSame(self::PAGE_ONE_CODES, array_column(array_column(array_column($result->items, 'meta'), 'movie'), 'code'));

        $first = $result->items[0];
        self::assertSame(self::BASE . '/products/taib-035', $first->url);
        self::assertSame('taib-035', $first->meta['movie']['external_id']);
        self::assertSame('AI美少女写真集 BEST COLLECTION vol.2', $first->meta['movie']['title']);
        self::assertSame(self::BASE . '/cdn/shop/files/TAIB-035_H1_1024x1024.jpg?v=1793874874', $first->meta['movie']['cover_url']);
        self::assertSame('TMA', $first->meta['movie']['metadata']['maker']);

        $last = $result->items[23];
        self::assertSame(self::BASE . '/products/34id-030', $last->url);
        self::assertSame('34ID-030', $last->meta['movie']['code']);
        self::assertSame('マゾメス肉便器淫穴性交映像 4時間', $last->meta['movie']['title']);
        self::assertSame(self::BASE . '/cdn/shop/files/34ID-030_01_1024x1024.jpg?v=1788940324', $last->meta['movie']['cover_url']);
    }

    public function test_every_card_title_cover_and_detail_url_matches_the_captured_pages(): void
    {
        foreach ([
            ['tma/listing-page1.html', self::BASE . '/collections/tma', 1, [
                '26bc6f0844ddc83126a47c12e6ff86e95911e52483535cbe833a29c3ef32b36a',
                'fc7703d1bbc1c1aeea344be080bd892bc5b1fc245b85c5cacaaa98ece2c233e8',
                '11be62230afe57a8f28181d37bf6865a367d1924b3a71d4f9243fe64db8a5e2e',
            ]],
            ['tma/listing-page2.html', self::BASE . '/collections/tma?page=2', 2, [
                'b6a86bed095410899eccfbea2d175a93c66f6269252776f4a374c57341fef6f7',
                '7b35f615af3e6aaf559f82e7f67666e2b8d6ef650d063e8aff2cf5e01fbd29ae',
                '548c79a01edfc7570412de4bbee32d329bd65f03e23cc05472b53c9c560aa018',
            ]],
        ] as [$fixture, $url, $page, [$titlesHash, $urlsHash, $coversHash]]) {
            $items = (new Listing($this->clientWithFixture($fixture)))
                ->execute(new CrawlRequestDto(url: $url, page: $page))->items;

            self::assertCount(24, $items);
            self::assertSame($titlesHash, $this->hash(array_map(static fn($item): string => $item->meta['movie']['title'], $items)));
            self::assertSame($urlsHash, $this->hash(array_map(static fn($item): string => $item->url, $items)));
            self::assertSame($coversHash, $this->hash(array_map(static fn($item): string => $item->meta['movie']['cover_url'], $items)));
        }
    }

    /** @param list<string> $values */
    private function hash(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function test_japanese_slug_cards_keep_their_title_and_have_no_code(): void
    {
        $result = (new Listing($this->clientWithFixture('tma/listing-page1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/collections/tma'));

        $card = $result->items[9]->meta['movie'];
        self::assertNull($card['code']);
        self::assertSame('壁尻個室アナル風俗-1', $card['external_id']);
        self::assertSame('壁尻個室アナル風俗', $card['title']);
        self::assertSame(
            self::BASE . '/products/' . rawurlencode('壁尻個室アナル風俗-1'),
            $result->items[9]->url,
        );
    }

    public function test_page_one_pagination_follows_the_site_pager(): void
    {
        $result = (new Listing($this->clientWithFixture('tma/listing-page1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/collections/tma'));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(24, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/collections/tma?page=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_page_two_returns_its_cards_and_the_site_pager_position(): void
    {
        $result = (new Listing($this->clientWithFixture('tma/listing-page2.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/collections/tma?page=2', page: 2));

        self::assertCount(24, $result->items);
        self::assertSame(self::PAGE_TWO_CODES, array_column(array_column(array_column($result->items, 'meta'), 'movie'), 'code'));
        self::assertSame(self::BASE . '/products/34id-029', $result->items[0]->url);
        self::assertSame(2, $result->page);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(24, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/collections/tma?page=3', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_last_page_without_a_next_link_reports_no_next_page(): void
    {
        $html = <<<'HTML'
            <div class="blocklayout">
              <div class="block product"><div class="main">
                <a class="img-link" href="/collections/tma/products/t38-073" title="Work"></a>
                <noscript><img src="//www.tma.co.jp/cdn/shop/files/T38-073_01_1024x1024.jpg?v=1"></noscript>
              </div><div class="sub"><a class="product-block-title" href="/collections/tma/products/t38-073">Work</a></div></div>
              <div class="block product"><div class="main"><a class="img-link" href="/collections/tma/products/t38-074"></a></div></div>
            </div>
            <div class="pagination">
              <span class="page current">24</span>
              <a class="page" href="/collections/tma?page=23">23</a>
              <span class="page current">&hellip;</span>
            </div>
            HTML;
        $result = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::BASE . '/collections/tma?page=24', page: 24));

        self::assertCount(1, $result->items);
        self::assertSame('T38-073', $result->items[0]->meta['movie']['code']);
        self::assertSame(24, $result->pagination->currentPage);
        self::assertSame(24, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_listing_without_product_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<div class="blocklayout"><p>No products</p></div>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/collections/tma'));
    }
}
