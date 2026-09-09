<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\JavLibrary\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Tests\TestCase;

final class JavLibraryAdapterTest extends TestCase
{
    public function test_listing_parses_movies_and_pagination(): void
    {
        $list = (new Listing($this->clientWithFixture('JavLibrary/listing-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/vl_newrelease.php',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertCount(20, $list->items);
        self::assertSame('javme3rasy', $list->items[0]->meta['movie']['external_id']);
        self::assertSame('Pacifier Prep School 110 - Mei Mizuki', $list->items[0]->meta['movie']['title']);
        self::assertSame('KV-328', $list->items[0]->meta['movie']['code']);
        self::assertStringContainsString('h_955kv328ps.jpg', (string) $list->items[0]->meta['movie']['cover_url']);
        self::assertGreaterThan(1, $list->pagination->lastPage ?? 0);
        self::assertSame(2, $list->pagination->nextPage);
    }

    public function test_detail_parses_movie_metadata(): void
    {
        $item = (new Detail($this->clientWithFixture('JavLibrary/detail-javme3rasy-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/javme3rasy.html',
            type: CrawlType::Detail,
        ));

        self::assertSame('javme3rasy', $item->meta['movie']['external_id']);
        self::assertSame('Pacifier Prep School 110 - Mei Mizuki', $item->meta['movie']['title']);
        self::assertSame('KV-328', $item->meta['movie']['code']);
        self::assertSame(109, $item->meta['movie']['duration']);
        self::assertSame('Mizuki Mei', $item->meta['movie']['performers'][0]['name']);
    }

    public function test_performer_listing_and_detail_parse_profiles(): void
    {
        $list = (new PerformerListing($this->clientWithFixture('JavLibrary/performer-listing-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/star_list.php?prefix=A',
            type: CrawlType::PerformerListing,
            page: 1,
        ));
        self::assertGreaterThan(50, count($list->items));
        self::assertSame('ayubu', $list->items[0]->meta['performer']['external_id']);
        self::assertSame('A・in', $list->items[0]->meta['performer']['name']);
        self::assertSame(2, $list->pagination->nextPage);

        $item = (new PerformerDetail($this->clientWithFixture('JavLibrary/performer-detail-ayubu-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/vl_star.php?s=ayubu',
            type: CrawlType::PerformerDetail,
        ));
        self::assertSame('A・in', $item->meta['performer']['name']);
        self::assertSame('ayubu', $item->meta['performer']['external_id']);
        self::assertNotEmpty($item->meta['performer']['raw_profile']);
    }

    public function test_listing_rejects_cloudflare_capture(): void
    {
        $this->expectException(CrawlBlockedException::class);
        (new Listing($this->clientWithFixture('JavLibrary/listing_page1.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/vl_newrelease.php',
            type: CrawlType::Listing,
        ));
    }

    public function test_detail_rejects_cloudflare_capture(): void
    {
        $this->expectException(CrawlBlockedException::class);
        (new Detail($this->clientWithFixture('JavLibrary/detail_ssis001.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/?v=javli3abc1',
            type: CrawlType::Detail,
        ));
    }

    public function test_performer_listing_rejects_cloudflare_capture(): void
    {
        $this->expectException(CrawlBlockedException::class);
        (new PerformerListing($this->clientWithFixture('JavLibrary/performer_listing_page1.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/star_list.php',
            type: CrawlType::Listing,
        ));
    }

    public function test_performer_detail_rejects_cloudflare_capture(): void
    {
        $this->expectException(CrawlBlockedException::class);
        (new PerformerDetail($this->clientWithFixture('JavLibrary/performer_detail_yua_mikami.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javlibrary.com/en/star_info.php?st=305870',
            type: CrawlType::Detail,
        ));
    }
}
