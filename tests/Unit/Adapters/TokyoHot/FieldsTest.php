<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\TokyoHot;

use JOOservices\CrawlerX\Adapters\TokyoHot\Types\Detail;
use JOOservices\CrawlerX\Adapters\TokyoHot\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/tokyohot/';

    public function test_the_product_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail('detail.html', 'n1700');

        self::assertSame('n1700', $movie['external_id']);
        self::assertSame('n1700', $movie['code']);
        self::assertSame('Tokyo Hot Real Incandescent Passion SEX Special =part11=', $movie['title']);
        self::assertSame('https://my.cdn.tokyo-hot.com/media/n1700/list_image/20220805223730/820x462_default.jpg', $movie['cover_url']);
        self::assertSame('2022-09-06', $movie['date']);
        self::assertSame(88, $movie['duration']);
        self::assertSame(['6092', '6042', '5938'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['Leo Saionji', 'Yuna Takeuchi', 'Anri Sugisaki'], array_column($movie['performers'], 'name'));
        self::assertSame('https://my.tokyo-hot.com/cast/6092/', $movie['performers'][0]['url']);
        self::assertSame(
            ['Bukkake', 'Oral', 'Creampie', 'Hand job / Blow job', 'Men and women Sex', 'Tokyo Hot Exclusive', 'AI-enhanced digital remaster 1080p'],
            $movie['tags'],
        );

        $meta = $movie['metadata'];
        self::assertSame('Tokyo-Hot', $meta['label']);
        self::assertSame('Tokyo-Hot', $meta['maker']);
        self::assertSame('2022/09/06', $meta['release_date_raw']);
        self::assertSame('https://my.cdn.tokyo-hot.com/media/samples/n1700.mp4', $meta['sample_video_url']);
        self::assertSame(['Bukkake', 'Oral', 'Creampie', 'Hand job / Blow job', 'Men and women Sex'], $meta['tag_groups']['Play']);
        self::assertSame(['Tokyo Hot Exclusive', 'AI-enhanced digital remaster 1080p'], $meta['tag_groups']['Theme']);
        self::assertSame('https://my.tokyo-hot.com/product/?vendor=Tokyo-Hot', $meta['label_url']);
        self::assertNotEmpty($meta['movie_digest']);
        self::assertStringContainsString('%20', $meta['movie_digest'][0]['url']);
        self::assertStringNotContainsString(' ', $meta['movie_digest'][0]['url']);
        self::assertSame(count($meta['movie_digest']), count($movie['screenshots']));
    }

    public function test_the_logo_is_not_a_cover_and_menu_words_are_not_the_products_data(): void
    {
        $movie = $this->detail('detail.html', 'n1700');

        self::assertStringNotContainsString('social.png', (string) $movie['cover_url']);
        self::assertNotContains('Models', array_column($movie['performers'], 'name'));
        self::assertNotContains('Porn Pics', $movie['tags']);
    }

    public function test_unknown_models_tags_and_the_description_are_read(): void
    {
        $movie = $this->detail('detail-tags.html', 'adl_301');

        self::assertSame('Skinny Hottie gets Anally Spooned', $movie['title']);
        self::assertSame([], $movie['performers']);
        self::assertSame('2026-10-18', $movie['date']);
        self::assertSame(29, $movie['duration']);
        self::assertStringStartsWith('This skinny, lovely babe has a new boyfriend.', (string) $movie['description']);
        self::assertContains('cumshot', $movie['tags']);
        self::assertSame('Stunning18', $movie['metadata']['label']);
        self::assertSame(['cumshot', 'Cum in mouth', 'Ass to Mouth'], $movie['metadata']['tag_groups']['Tags']);
    }

    public function test_series_and_unknown_rows_are_kept(): void
    {
        $html = '<html><body><div id="main"><div class="contents"><h2>Name</h2><dl class="info">'
            . '<dt>Series</dt><dd>Series One</dd><dt>Special</dt><dd>extra</dd><dt>Release Date</dt><dd>2020/01/02</dd><dt>Duration</dt><dd>01:00:00</dd><dt>Product ID</dt><dd>x1</dd></dl></div></div></body></html>';

        $movie = $this->detailHtml($html, 'x1');

        self::assertSame('Series One', $movie['metadata']['series']);
        self::assertSame(['Special' => 'extra'], $movie['metadata']['fields']);
    }

    public function test_the_home_page_cards_carry_id_title_cover_and_description(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing.html'))))
            ->execute(new CrawlRequestDto(url: 'https://my.tokyo-hot.com/index', type: CrawlType::Listing));

        self::assertGreaterThan(10, count($result->items));
        $byId = [];
        foreach ($result->items as $item) {
            $byId[$item->meta['movie']['external_id']] = $item->meta['movie'];
        }
        self::assertSame('Skinny Hottie gets Anally Spooned', $byId['adl_301']['title']);
        self::assertSame('https://my.cdn.tokyo-hot.com/media/adl_301/list_image/20261014211642/460x268_default.jpg', $byId['adl_301']['cover_url']);
        self::assertStringStartsWith('This skinny, lovely babe', (string) $byId['adl_301']['description']);
        self::assertSame('https://my.tokyo-hot.com/product/adl_301/', $result->items[array_search('adl_301', array_keys($byId), true)]->url);
    }

    public function test_the_product_list_has_page_numbers(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing-products.html'))))
            ->execute(new CrawlRequestDto(url: 'https://my.tokyo-hot.com/product/', type: CrawlType::Listing));

        self::assertGreaterThan(20, count($result->items));
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame('https://my.tokyo-hot.com/product/?page=2', $result->pagination->nextUrl);
        self::assertGreaterThan(100, $result->pagination->lastPage);
    }

    public function test_a_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body></body></html>')))
            ->execute(new CrawlRequestDto(url: 'https://my.tokyo-hot.com/product/', type: CrawlType::Listing));
    }

    /** @return array<string, mixed> */
    private function detail(string $fixture, string $id): array
    {
        return $this->detailHtml((string) file_get_contents(self::FIXTURES . $fixture), $id);
    }

    /** @return array<string, mixed> */
    private function detailHtml(string $html, string $id): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://my.tokyo-hot.com/product/' . $id . '/', type: CrawlType::Detail))
            ->meta['movie'];
    }
}
