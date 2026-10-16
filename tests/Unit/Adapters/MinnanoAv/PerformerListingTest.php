<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\MinnanoAv;

use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PerformerListingTest extends TestCase
{
    private const URL = 'https://www.minnano-av.com/actress_list.php?page=1';

    public function test_json_ld_people_are_listed_with_decoded_names_and_ids(): void
    {
        $html = '<html><head><script type="application/ld+json">'
            . '{"@type":"Person","name":"Rio &amp; Co","url":"https://www.minnano-av.com/actress123.html"}'
            . '</script></head><body></body></html>';

        $result = $this->execute($html);

        self::assertCount(1, $result->items);
        self::assertSame('https://www.minnano-av.com/actress123.html', $result->items[0]->url);
        self::assertSame('Rio & Co', $result->items[0]->meta['performer']['name']);
        self::assertSame('123', $result->items[0]->meta['performer']['external_id']);
    }

    public function test_plain_actress_urls_are_used_when_no_json_ld_person_exists(): void
    {
        $html = '<html><body>see https://www.minnano-av.com/actress77.html and '
            . 'https://www.minnano-av.com/actress78.html</body></html>';

        $result = $this->execute($html);

        self::assertSame(
            ['https://www.minnano-av.com/actress77.html', 'https://www.minnano-av.com/actress78.html'],
            array_map(static fn($item) => $item->url, $result->items),
        );
        self::assertSame('77', $result->items[0]->meta['performer']['external_id']);
    }

    public function test_table_links_are_listed_with_their_text_and_blank_or_foreign_links_are_ignored(): void
    {
        $html = '<html><body><table class="tbllist">'
            . '<tr><td><a href="/actress5.html"> Aoi  Mei </a></td></tr>'
            . '<tr><td><a href="  ">blank</a></td></tr>'
            . '<tr><td><a href="/actress_list.html">not an actress page</a></td></tr>'
            . '<tr><td><a href="/actress5.html">duplicate with a name already kept</a></td></tr>'
            . '</table></body></html>';

        $result = $this->execute($html);

        self::assertCount(1, $result->items);
        self::assertSame('https://www.minnano-av.com/actress5.html', $result->items[0]->url);
        self::assertSame('Aoi Mei', $result->items[0]->meta['performer']['name']);
    }

    public function test_an_unnamed_link_is_replaced_by_a_later_named_link_for_the_same_actress(): void
    {
        $html = '<html><body><table class="tbllist">'
            . '<tr><td><a href="/actress9.html"></a></td></tr>'
            . '<tr><td><a href="/actress9.html">Named Later</a></td></tr>'
            . '</table></body></html>';

        $result = $this->execute($html);

        self::assertCount(1, $result->items);
        self::assertSame('Named Later', $result->items[0]->meta['performer']['name']);
    }

    public function test_next_page_comes_from_a_link_rel_next_element(): void
    {
        $html = '<html><head><link rel="next" href="/actress_list.php?page=2"></head><body>'
            . 'https://www.minnano-av.com/actress1.html</body></html>';

        $result = $this->execute($html);

        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame('https://www.minnano-av.com/actress_list.php?page=2', $result->pagination->nextUrl);
        self::assertSame(2, $result->pagination->nextPage);
    }

    public function test_next_page_comes_from_the_pagination_next_anchor_when_no_link_rel_exists(): void
    {
        $html = '<html><body><div class="pagination"><a class="next" href="/actress_list.php?page=3">next</a></div>'
            . 'https://www.minnano-av.com/actress1.html</body></html>';

        $result = $this->execute($html);

        self::assertSame('https://www.minnano-av.com/actress_list.php?page=3', $result->pagination->nextUrl);
        self::assertSame(3, $result->pagination->nextPage);
    }

    public function test_next_page_is_read_from_raw_html_when_the_dom_has_no_usable_link(): void
    {
        $html = '<html><body>'
            . '<!-- <link rel="next" href="/actress_list.php?page=4"> -->'
            . 'https://www.minnano-av.com/actress1.html</body></html>';

        $result = $this->execute($html);

        self::assertSame('https://www.minnano-av.com/actress_list.php?page=4', $result->pagination->nextUrl);
    }

    public function test_there_is_no_next_page_and_the_page_number_is_null_without_a_page_query(): void
    {
        $result = $this->execute('<html><body>https://www.minnano-av.com/actress1.html</body></html>');

        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertNull($result->pagination->nextPage);
    }

    public function test_next_url_without_a_query_string_gives_a_null_page_number(): void
    {
        $html = '<html><head><link rel="next" href="/actress_list.php"></head><body>'
            . 'https://www.minnano-av.com/actress1.html</body></html>';

        $result = $this->execute($html);

        self::assertTrue($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextPage);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function lastPageSources(): iterable
    {
        yield 'page info element' => [
            '<div class="pagination"><span class="page_info">1 / 42 ページ</span></div>',
            42,
        ];
        yield 'page jump input max' => [
            '<input class="page_jump_input" type="number" max="17">',
            17,
        ];
        yield 'text anywhere in the page' => [
            '<p>3 / 8 ページ</p>',
            8,
        ];
    }

    #[DataProvider('lastPageSources')]
    public function test_last_page_is_detected_from_each_supported_source(string $fragment, int $expected): void
    {
        $result = $this->execute('<html><body>' . $fragment . 'https://www.minnano-av.com/actress1.html</body></html>');

        self::assertSame($expected, $result->pagination->lastPage);
    }

    public function test_last_page_is_null_when_the_page_does_not_state_it(): void
    {
        $result = $this->execute('<html><body>https://www.minnano-av.com/actress1.html</body></html>');

        self::assertNull($result->pagination->lastPage);
    }

    public function test_a_blocked_status_is_reported_as_unavailable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('minnanoav performer_listing page is blocked or unavailable.');

        (new PerformerListing($this->clientWithHtml('<html></html>', 403)))->execute($this->request());
    }

    public function test_a_cloudflare_challenge_page_is_reported_as_unavailable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked or unavailable');

        (new PerformerListing($this->clientWithHtml('<html><title>Just a moment...</title></html>')))
            ->execute($this->request());
    }

    public function test_an_empty_body_is_reported_as_empty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('minnanoav performer_listing page is empty.');

        (new PerformerListing($this->clientWithHtml('   ')))->execute($this->request());
    }

    private function request(): CrawlRequestDto
    {
        return new CrawlRequestDto(url: self::URL, type: CrawlType::PerformerListing);
    }

    private function execute(string $html): \JOOservices\CrawlerX\Dto\CrawlListResultDto
    {
        return (new PerformerListing($this->clientWithHtml($html)))->execute($this->request());
    }
}
