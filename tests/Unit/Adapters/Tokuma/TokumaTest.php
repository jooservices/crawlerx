<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Tokuma;

use JOOservices\CrawlerX\Adapters\Tokuma\TokumaCrawler;
use JOOservices\CrawlerX\Adapters\Tokuma\Types\Detail;
use JOOservices\CrawlerX\Adapters\Tokuma\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class TokumaTest extends TestCase
{
    private const BASE = 'https://www.tokuma.jp';

    public function test_listing_page_one_extracts_book_cards_and_real_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('tokuma/listing-page1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/search/new.html', type: CrawlType::Listing));

        self::assertSame('movie', $result->entityType);
        self::assertCount(12, $result->items);
        self::assertSame(1, $result->page);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(36, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/search/index.php?page=2&new_status=true&c=1', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);

        $book = $result->items[0];
        self::assertSame(self::BASE . '/book/b681651.html', $book->url);
        self::assertSame('movie', $book->entityType);
        self::assertSame('b681651', $book->meta['movie']['external_id']);
        self::assertSame('b681651', $book->meta['movie']['code']);
        self::assertSame('園梨音ファースト写真集 ＬＯＶＥ ＥＳＣＡＰＥ', $book->meta['movie']['title']);
        self::assertSame(self::BASE . '/images/book/681651.jpg', $book->meta['movie']['cover_url']);
        self::assertNull($book->meta['movie']['date']);
        self::assertSame([], $book->meta['movie']['performers']);
        self::assertSame(['amount' => 4620.0, 'currency' => 'JPY', 'label' => '定価4,620円（税込）'], $book->meta['movie']['metadata']['price']);
        self::assertSame([
            ['name' => '園梨音', 'url' => self::BASE . '/author/a294893.html', 'role' => '著・文・その他'],
            ['name' => 'Gota Suzuki', 'url' => self::BASE . '/author/a288743.html', 'role' => '写真'],
        ], $book->meta['movie']['metadata']['authors']);

        $novel = null;
        $placeholder = null;
        foreach ($result->items as $item) {
            if ($item->meta['movie']['external_id'] === 'b681829') {
                $novel = $item->meta['movie'];
            }
            if ($item->meta['movie']['external_id'] === 'b681494') {
                $placeholder = $item->meta['movie'];
            }
        }
        self::assertIsArray($novel);
        self::assertSame([], $novel['performers']);
        self::assertSame(['野山歩', 'ツヅル'], array_column($novel['metadata']['authors'], 'name'));
        self::assertIsArray($placeholder);
        self::assertNull($placeholder['cover_url']);
    }

    public function test_listing_page_two_reads_current_page_and_items_from_captured_markup(): void
    {
        $url = self::BASE . '/search/index.php?page=2&new_status=true&c=1';
        $result = (new Listing($this->clientWithFixture('tokuma/listing-page2.html')))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing));

        self::assertSame(2, $result->page);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(36, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/search/index.php?page=3&new_status=true&c=1', $result->pagination->nextUrl);
        self::assertSame(self::BASE . '/book/b681270.html', $result->items[3]->url);
        self::assertSame('爽香１ｓｔ写真集 ウルトラメテオラ', $result->items[3]->meta['movie']['title']);
        self::assertSame(self::BASE . '/images/book/681270.jpg', $result->items[3]->meta['movie']['cover_url']);
    }

    public function test_listing_page_range_fallback_uses_inclusive_width_and_keeps_partial_last_page(): void
    {
        $html = str_replace(
            'value="12" selected',
            'value="12"',
            $this->loadFixture('tokuma/listing-page2.html'),
        );
        $url = self::BASE . '/search/index.php?page=2&new_status=true&c=1';
        $pageTwo = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing));
        self::assertSame(36, $pageTwo->pagination->lastPage);

        $lastPageHtml = str_replace('13～24件', '421～431件', $html);
        $lastPageHtml = str_replace('<strong>&nbsp;2&nbsp;</strong>', '<strong>&nbsp;36&nbsp;</strong>', $lastPageHtml);
        $lastPageHtml = str_replace('>&nbsp;5&nbsp;</a>', '>&nbsp;35&nbsp;</a>', $lastPageHtml);
        $lastPage = (new Listing($this->clientWithHtml($lastPageHtml)))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing));
        self::assertSame(36, $lastPage->pagination->currentPage);
        self::assertSame(36, $lastPage->pagination->lastPage);
    }

    public function test_detail_extracts_every_labelled_field_and_the_single_work_image(): void
    {
        $result = (new Detail($this->clientWithFixture('tokuma/detail-b681270.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/book/b681270.html', type: CrawlType::Detail));
        $movie = $result->meta['movie'];

        self::assertSame(self::BASE . '/book/b681270.html', $result->url);
        self::assertSame('movie', $result->entityType);
        self::assertSame('b681270', $movie['external_id']);
        self::assertSame('9784198663094', $movie['code']);
        self::assertSame('爽香１ｓｔ写真集 ウルトラメテオラ', $movie['title']);
        self::assertSame(self::BASE . '/images/book/681270.jpg', $movie['cover_url']);
        self::assertStringContainsString('グラビアモデル・爽香の待望のファースト写真集。', $movie['description']);
        self::assertSame('2026-11-11', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['一般書', '芸能・タレント', '発行元出版社', '徳間書店'], $movie['tags']);

        self::assertCount(1, $movie['screenshots']);
        self::assertSame(self::BASE . '/images/book/681270.jpg', $movie['screenshots'][0]['url']);
        self::assertSame(['role' => 'cover'], $movie['screenshots'][0]['metadata']);
        self::assertSame(1, $movie['metadata']['image_count']);
        self::assertSame('Tokuma Shoten', $movie['metadata']['maker']);
        self::assertSame('9784198663094', $movie['metadata']['isbn']);
        self::assertSame([
            ['name' => '爽香', 'url' => self::BASE . '/author/a294672.html', 'role' => '著・文・その他'],
            ['name' => '高橋慶佑', 'url' => self::BASE . '/author/a283608.html', 'role' => '写真'],
        ], $movie['metadata']['authors']);
        self::assertSame([
            ['name' => '一般書', 'url' => self::BASE . '/search/g15616.html'],
            ['name' => '芸能・タレント', 'url' => self::BASE . '/search/g15495.html'],
            ['name' => '発行元出版社', 'url' => self::BASE . '/search/g15709.html'],
            ['name' => '徳間書店', 'url' => self::BASE . '/search/g15710.html'],
        ], $movie['metadata']['genres']);
        self::assertSame('一般書', $movie['metadata']['series']);
        self::assertSame([['name' => '一般書', 'url' => self::BASE . '/search/s14676.html']], $movie['metadata']['series_links']);
        self::assertSame('A4・112ページ', $movie['metadata']['format']);
        self::assertSame(112, $movie['metadata']['pages']);
        self::assertSame(['amount' => 4180.0, 'currency' => 'JPY', 'label' => '4,180円（税込）'], $movie['metadata']['price']);
        self::assertSame([
            '著者' => '爽香 著・文・その他 高橋慶佑 写真',
            'ジャンル' => '一般書 芸能・タレント 発行元出版社 > 徳間書店',
            'シリーズ' => '一般書',
            '出版年月日' => '2026/11/11',
            'ISBN' => '9784198663094',
            '判型・ページ数' => 'A4・112ページ',
            '定価' => '4,180円（税込）',
        ], $movie['metadata']['source_fields']);
    }

    public function test_detail_ignores_the_site_placeholder_as_cover_and_screenshot(): void
    {
        $html = str_replace(
            '../images/book/681270.jpg',
            '../images/common/now_printing_150x187.gif',
            $this->loadFixture('tokuma/detail-b681270.html'),
        );
        $result = (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::BASE . '/book/b681270.html', type: CrawlType::Detail));
        $movie = $result->meta['movie'];

        self::assertNull($movie['cover_url']);
        self::assertSame([], $movie['screenshots']);
        self::assertSame(0, $movie['metadata']['image_count']);
    }

    public function test_url_detection_matches_only_captured_listing_and_detail_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('tokuma');

        self::assertInstanceOf(TokumaCrawler::class, $crawler);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/search/new.html')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/search/index.php?page=2&new_status=true&c=1')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/book/b681270.html')->crawlType);
        self::assertFalse($crawler->detectUrl(self::BASE . '/search/g15495.html')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/search/new.html?order=price_asc')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/search/index.php?page=2&new_status=true&c=1&genre=7')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/book/b681270')->matched);
    }
}
