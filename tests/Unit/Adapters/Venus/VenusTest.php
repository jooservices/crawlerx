<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Venus;

use JOOservices\CrawlerX\Adapters\Venus\Types\Detail;
use JOOservices\CrawlerX\Adapters\Venus\Types\Listing;
use JOOservices\CrawlerX\Adapters\Venus\VenusCrawler;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class VenusTest extends TestCase
{
    private const BASE = 'https://venus-av.com';

    private const DETAIL_URL = 'https://venus-av.com/products/%e3%80%8c%e3%81%8a%e3%81%b0%e3%81%95%e3%82%93%e3%81%ae%e4%b8%8b%e7%9d%80%e3%81%a7%e8%88%88%e5%A5%ae%e3%81%99%e3%82%8b%e3%81%ae%ef%bc%9f%e3%80%8d%e8%84%b1%e3%81%8e%e3%81%9f%e3%81%a6%e3%81%ae-44/';

    public function test_listing_page_one_extracts_card_fields_and_real_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('venus/listing-page-1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/all/'));

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(20, $result->items);

        $first = $result->items[0];
        $movie = $first->meta['movie'];
        self::assertSame('https://venus-av.com/products/s%e7%b4%9a%e7%86%9f%e5%a5%b3%e3%82%b3%e3%83%b3%e3%83%97%e3%83%aa%e3%83%bc%e3%83%88%e3%83%95%e3%82%a1%e3%82%a4%e3%83%ab-%e5%90%b9%e7%9f%b3%e3%82%8c%e3%81%aa-6%e6%99%82%e9%96%93-%e5%85%b6%e4%b9%8b/', $first->url);
        self::assertSame('VEQ-289', $movie['external_id']);
        self::assertSame('VEQ-289', $movie['code']);
        self::assertSame('S級熟女コンプリートファイル 吹石れな 6時間 其之弐', $movie['title']);
        self::assertSame('https://venus-av.com/wp-content/uploads/old/VEQ-289.jpg', $movie['cover_url']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(['吹石れな'], array_column($movie['performers'], 'name'));
        self::assertSame('VENUS', $movie['metadata']['maker']);
        self::assertSame([
            '品番' => 'VEQ-289',
            '出演女優' => '吹石れな',
            '発売日' => '発売日：2026年12月1日',
        ], $movie['metadata']['source_fields']);

        $venus388Item = array_values(array_filter(
            $result->items,
            static fn($item): bool => $item->meta['movie']['code'] === 'VENX-388',
        ))[0];
        $venus388 = $venus388Item->meta['movie'];
        self::assertStringContainsString('/products/', $venus388Item->url);
        self::assertSame('https://venus-av.com/wp-content/uploads/old/VENX-388.jpg', $venus388['cover_url']);
        self::assertSame('永崎麗香', $venus388['performers'][0]['name']);
        self::assertSame('2026-12-01', $venus388['date']);

        self::assertSame(172, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/all/page/2/', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_listing_page_two_reads_page_and_next_link_from_the_page(): void
    {
        $result = (new Listing($this->clientWithFixture('venus/listing-page-2.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/all/page/2/'));

        self::assertSame(2, $result->page);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(172, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/all/page/3/', $result->pagination->nextUrl);
        self::assertSame('VEMA-266', $result->items[0]->meta['movie']['code']);
        self::assertSame('月野江すい', $result->items[0]->meta['movie']['performers'][0]['name']);
        self::assertSame('2026-10-06', $result->items[0]->meta['movie']['date']);
    }

    public function test_detail_extracts_all_labeled_fields_and_full_size_product_image(): void
    {
        $result = (new Detail($this->clientWithFixture('venus/detail-VENX-388.html')))
            ->execute(new CrawlRequestDto(url: self::DETAIL_URL));
        $movie = $result->meta['movie'];

        self::assertSame(self::DETAIL_URL, $result->url);
        self::assertSame('movie', $result->entityType);
        self::assertSame('VENX-388', $movie['external_id']);
        self::assertSame('VENX-388', $movie['code']);
        self::assertSame('「おばさんの下着で興奮するの？」脱ぎたてのパンティで甥っ子の精子を一滴残らず搾りとる叔母', $movie['title']);
        self::assertSame('https://venus-av.com/wp-content/uploads/old/VENX-388.jpg', $movie['cover_url']);
        self::assertSame('叔父が長期出張で家を空けることになり叔母・麗香が一人になるのを心配した家族の勧めで、叔母の家から就職先が近い剛が下宿することになった。仕方なくを装ったが本当は秘かに憧れていた麗香と暮らせることが嬉しくて仕方がない剛。同居して数日が経ちムラムラがピークを迎えた剛は入浴した麗香が脱いだばかりのパンティでシコシコしてしまう。興奮のあまり夢中になり過ぎた剛は入浴を終え出てきた麗香に見つかり大ピンチを迎えるのだが…。', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame(['永崎麗香'], array_column($movie['performers'], 'name'));
        self::assertSame([], $movie['tags']);
        self::assertSame([], $movie['screenshots']);
        self::assertSame('VENUS', $movie['metadata']['maker']);
        self::assertSame('INCEST', $movie['metadata']['label']);
        self::assertSame([
            '作品紹介' => '叔父が長期出張で家を空けることになり叔母・麗香が一人になるのを心配した家族の勧めで、叔母の家から就職先が近い剛が下宿することになった。仕方なくを装ったが本当は秘かに憧れていた麗香と暮らせることが嬉しくて仕方がない剛。同居して数日が経ちムラムラがピークを迎えた剛は入浴した麗香が脱いだばかりのパンティでシコシコしてしまう。興奮のあまり夢中になり過ぎた剛は入浴を終え出てきた麗香に見つかり大ピンチを迎えるのだが…。',
            '出演女優' => '永崎麗香',
            '品番・分数' => 'VENX-388',
            'レーベル' => 'INCEST',
            '発売日' => '2026年12月1日',
        ], $movie['metadata']['source_fields']);
        $encoded = (string) json_encode($movie, JSON_UNESCAPED_UNICODE);
        foreach (['VOD-049', 'VENX-371', 'productsPickupList', 'headerLogo'] as $unrelated) {
            self::assertStringNotContainsString($unrelated, $encoded);
        }
    }

    public function test_url_detection_matches_only_captured_listing_and_product_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('venus');

        self::assertInstanceOf(VenusCrawler::class, $crawler);
        foreach ([
            '/all' => CrawlType::Listing,
            '/all/page/2/' => CrawlType::Listing,
            '/products/sample-product/' => CrawlType::Detail,
        ] as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }

        self::assertFalse($crawler->detectUrl(self::BASE . '/new-release/')->matched);
        self::assertSame('venus', $crawler->name());
    }
}
