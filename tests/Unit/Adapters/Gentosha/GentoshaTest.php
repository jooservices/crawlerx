<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Gentosha;

use JOOservices\CrawlerX\Adapters\Gentosha\GentoshaCrawler;
use JOOservices\CrawlerX\Adapters\Gentosha\Types\Detail;
use JOOservices\CrawlerX\Adapters\Gentosha\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Support\MovieMeta;
use JOOservices\CrawlerX\Tests\TestCase;

final class GentoshaTest extends TestCase
{
    private const BASE = 'https://www.gentosha.co.jp';

    public function test_listing_extracts_real_page_one_fields_and_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('gentosha/listing-page1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/book/new/'));

        self::assertCount(20, $result->items);
        self::assertSame(1, $result->page);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(524, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/book/new/?p=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);

        $item = $result->items[0];
        $movie = $item->meta['movie'];
        self::assertSame(self::BASE . '/book/detail/9784344436077/', $item->url);
        self::assertSame('9784344436077', $movie['external_id']);
        self::assertSame('9784344436077', $movie['code']);
        self::assertSame('ダブルマザー', $movie['title']);
        self::assertSame('2026-11-12', $movie['date']);
        self::assertSame('https://img.gentosha.co.jp/_cover_/9784344436077.jpg?p=li&v=00', $movie['cover_url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['文庫', '電子書籍'], $movie['tags']);

        $metadata = $movie['metadata'];
        self::assertSame([['name' => '辻堂ゆめ', 'url' => null]], $metadata['authors']);
        self::assertSame(['文庫', '電子書籍'], $metadata['formats']);
        self::assertSame(MovieMeta::price(858.0, 'JPY', '定価858円（本体780円＋税）'), $metadata['price']);
        self::assertSame([
            '発行形態' => '発行形態 文庫 電子書籍',
            'タイトル' => 'タイトル ダブルマザー',
            '著者' => '著者 辻堂ゆめ',
            '価格' => '価格 定価858円（本体780円＋税）',
            '発売日' => '発売日 2026.11.12',
        ], $metadata['source_fields']);
    }

    public function test_listing_uses_captured_page_two_and_site_pager_values(): void
    {
        $result = (new Listing($this->clientWithFixture('gentosha/listing-page2.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/book/new/?p=2'));

        self::assertCount(20, $result->items);
        self::assertSame(2, $result->page);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(524, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/book/new/?p=3', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/book/detail/9784344046481/', $result->items[0]->url);
        self::assertSame('9784344046481', $result->items[0]->meta['movie']['external_id']);
    }

    public function test_detail_extracts_real_book_fields_and_only_work_images(): void
    {
        $result = (new Detail($this->clientWithFixture('gentosha/detail-9784344046368.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/book/detail/9784344046368/'));
        $movie = $result->meta['movie'];

        self::assertSame(self::BASE . '/book/detail/9784344046368/', $result->url);
        self::assertSame('9784344046368', $movie['external_id']);
        self::assertSame('9784344046368', $movie['code']);
        self::assertSame('FRUITS ZIPPER 4th Anniversary写真集『SYNERGY』', $movie['title']);
        self::assertStringStartsWith('“NEW KAWAII”を体現する7人組アイドルグループ', $movie['description']);
        self::assertStringContainsString('FRUITS ZIPPERだからこそ表現できる', $movie['description']);
        self::assertSame('2026-10-31', $movie['date']);
        self::assertSame('https://img.gentosha.co.jp/_cover_/9784344046368.jpg?p=book-hq&v=01', $movie['cover_url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['写真集・画集'], $movie['tags']);

        self::assertSame([
            [
                'url' => 'https://img.gentosha.co.jp/_cover_/9784344046368.jpg?p=book-hq&v=01',
                'thumbnail_url' => 'https://img.gentosha.co.jp/_cover_/9784344046368.jpg?p=book-thumb&v=01',
                'metadata' => ['position' => 0],
            ],
            [
                'url' => 'https://img.gentosha.co.jp/_book_/9784344046368-01.jpg?p=post-body',
                'thumbnail_url' => null,
                'metadata' => ['position' => 1],
            ],
        ], $movie['screenshots']);

        $metadata = $movie['metadata'];
        self::assertSame(MovieMeta::price(3850.0, 'JPY', '定価 3,850円（本体3,500円＋税）'), $metadata['price']);
        self::assertSame([['name' => 'FRUITS ZIPPER', 'url' => self::BASE . '/author/detail/033382/', 'external_id' => '033382']], $metadata['authors']);
        self::assertSame(['写真集・画集'], $metadata['category']);
        self::assertSame('幻冬舎', $metadata['publisher']);
        self::assertSame('https://schema.org/Hardcover', $metadata['book_format']);
        self::assertSame('https://img.gentosha.co.jp/_cover_/9784344046368.jpg?p=book-thumb&v=01', $metadata['cover_thumbnail_url']);
        self::assertSame('※価格、発売日は紙書籍のものです。', $metadata['price_note']);
        self::assertCount(10, $metadata['purchase_links']);
        self::assertSame(['name' => 'amazon', 'url' => 'https://www.amazon.co.jp/dp/4344046366/?tag=gentoshaoffi-22', 'format' => 'pbook'], $metadata['purchase_links'][0]);
        self::assertSame([
            '発行形態' => '発行形態 単行本',
            'ISBN' => 'ISBN 9784344046368',
            'タイトル' => 'タイトル FRUITS ZIPPER 4th Anniversary写真集『SYNERGY』',
            '著者' => '著者 FRUITS ZIPPER',
            '分類' => '分類 写真集・画集',
            '価格' => '価格 定価 3,850円（本体3,500円＋税）',
            '発売日' => '発売日 2026.10.31 発売',
        ], $metadata['source_fields']);
    }

    public function test_only_captured_listing_and_detail_paths_are_detected(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('gentosha');

        self::assertInstanceOf(GentoshaCrawler::class, $crawler);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/book/new/')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/book/new/?p=2')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/book/detail/9784344046368/')->crawlType);
        self::assertFalse($crawler->detectUrl(self::BASE . '/book/photo/')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/book/detail/not-an-isbn/')->matched);
    }
}
