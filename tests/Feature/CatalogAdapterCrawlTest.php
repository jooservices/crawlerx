<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;
use PHPUnit\Framework\Attributes\DataProvider;

final class CatalogAdapterCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function detailProvider(): iterable
    {
        yield 'javdb' => ['https://javdb.com/v/a8yV0p', 'javdb/detail.html', 'a8yV0p'];
        yield 'duga' => ['https://duga.jp/ppv/vrpandemic-0023/', 'duga/detail.html', 'vrpandemic-0023'];
        yield 'fc2' => ['https://adult.contents.fc2.com/article/4968399/', 'fc2/detail.html', 'FC2-PPV-4968399'];
        yield 'tokyohot' => ['https://my.tokyo-hot.com/product/crazyasia097085/', 'tokyohot/detail.html', 'crazyasia097085'];
        yield 'caribbeancom' => ['https://en.caribbeancom.com/eng/moviepages/080826-001/index.html', 'caribbeancom/detail.html', '080826-001'];
        yield 'heyzo' => ['https://en.heyzo.com/moviepages/3927/index.html', 'heyzo/detail.html', '3927'];
    }

    #[DataProvider('detailProvider')]
    public function test_new_catalog_detail_uses_typed_movie_contract(string $url, string $fixture, string $externalId): void
    {
        FixtureResponder::for('GET', $url)->file($fixture);

        $item = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $item);
        self::assertSame('movie', $item->entityType);
        self::assertSame(['movie'], array_keys($item->meta));
        self::assertSame($externalId, $item->meta['movie']['external_id']);
        self::assertNotSame('', trim((string) $item->meta['movie']['title']));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function ageGateProvider(): iterable
    {
        yield 'javbus' => ['https://www.javbus.com/', 'https://www.javbus.com/en/', 'javbus/listing-live.html'];
        yield 'duga' => ['https://duga.jp/', 'https://duga.jp/main/', 'duga/listing.html'];
        yield 'tokyohot' => ['https://my.tokyo-hot.com/', 'https://my.tokyo-hot.com/index?lang=en', 'tokyohot/listing.html'];
        yield 'caribbeancom' => ['https://en.caribbeancom.com/', 'https://en.caribbeancom.com/eng/index2.htm', 'caribbeancom/listing.html'];
        yield 'heyzo' => ['https://en.heyzo.com/', 'https://en.heyzo.com/listpages/all_1.html', 'heyzo/listing.html'];
    }

    #[DataProvider('ageGateProvider')]
    public function test_root_listing_bypasses_age_gate_with_accepted_route(
        string $requestedUrl,
        string $acceptedUrl,
        string $fixture,
    ): void {
        FixtureResponder::for('GET', $acceptedUrl)->file($fixture);

        $list = CrawlerX::url($requestedUrl)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $list);
        self::assertSame($acceptedUrl, $list->url);
        self::assertSame('movie', $list->entityType);
        self::assertNotEmpty($list->items);
    }
}
