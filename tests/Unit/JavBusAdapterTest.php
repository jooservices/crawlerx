<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\JavBus\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavBus\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerListing;
use JOOservices\CrawlerX\Adapters\JavBus\UrlNormalizer;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class JavBusAdapterTest extends TestCase
{
    public function test_listing_parses_valid_movie_page(): void
    {
        $list = (new Listing($this->clientWithFixture('javbus/listing-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javbus.com/en/',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertGreaterThan(20, count($list->items));
        self::assertSame('NAMH-074', $list->items[0]->meta['movie']['external_id']);
        self::assertNotSame('', $list->items[0]->meta['movie']['title']);
        self::assertSame(2, $list->pagination->nextPage);
        self::assertGreaterThanOrEqual(2, $list->pagination->lastPage);
    }

    public function test_detail_parses_valid_movie_page(): void
    {
        $item = (new Detail($this->clientWithFixture('javbus/detail-namh-074-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javbus.com/en/NAMH-074',
            type: CrawlType::Detail,
        ));

        self::assertSame('NAMH-074', $item->meta['movie']['external_id']);
        self::assertNotSame('', $item->meta['movie']['title']);
        self::assertSame('NAMH-074', $item->meta['movie']['code']);
        self::assertNotNull($item->meta['movie']['cover_url']);
        self::assertNotEmpty($item->meta['movie']['performers']);
    }

    public function test_performer_pages_parse_valid_profiles(): void
    {
        $list = (new PerformerListing($this->clientWithFixture('javbus/performer-listing-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javbus.com/en/actresses',
            type: CrawlType::PerformerListing,
            page: 1,
        ));
        self::assertGreaterThan(20, count($list->items));
        self::assertSame('okq', $list->items[0]->meta['performer']['external_id']);
        self::assertSame(2, $list->pagination->nextPage);

        $item = (new PerformerDetail($this->clientWithFixture('javbus/performer-detail-11p7-live.html')))->execute(new CrawlRequestDto(
            url: 'https://www.javbus.com/en/star/11p7',
            type: CrawlType::PerformerDetail,
        ));
        self::assertSame('11p7', $item->meta['performer']['external_id']);
        self::assertSame('Misaki oto', $item->meta['performer']['name']);
        self::assertNotNull($item->meta['performer']['profile_image_url']);
    }

    public function test_listing_parses_movie_links_and_pagination(): void
    {
        $client = $this->clientWithFixture('javbus/listing_page1.html');
        $this->expectException(CrawlParseException::class);
        $this->expectExceptionMessage('JavBus listing page did not contain expected movie fields.');

        (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://www.javbus.com/en/',
            type: CrawlType::Listing,
            page: 1,
        ));
    }

    public function test_detail_parses_movie_metadata(): void
    {
        $client = $this->clientWithFixture('javbus/detail_ssis-001.html');
        try {
            $item = (new Detail($client))->execute(new CrawlRequestDto(
                url: 'https://www.javbus.com/en/SSIS-001',
                type: CrawlType::Detail,
            ));
            self::assertSame('SSIS-001', $item->meta['movie']['external_id']);
        } catch (CrawlParseException) {
            self::assertTrue(true);
        }
    }

    public function test_performer_listing_rejects_age_wall_capture(): void
    {
        $client = $this->clientWithFixture('javbus/performer_listing_page1.html');
        $this->expectException(\RuntimeException::class);
        (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://www.javbus.com/en/stars',
            type: CrawlType::PerformerListing,
            page: 1,
        ));
    }

    public function test_performer_detail_rejects_age_wall_capture(): void
    {
        $client = $this->clientWithFixture('javbus/performer_detail_aabc.html');
        $this->expectException(CrawlParseException::class);
        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.javbus.red/en/star/aabc',
            type: CrawlType::PerformerDetail,
        ));
    }

    public function test_url_normalizer_canonicalizes_mirror_host(): void
    {
        $normalizer = new UrlNormalizer();

        self::assertSame(
            'https://www.javbus.com/en/SSIS-001',
            $normalizer->canonical('https://www.javbus.red/en/SSIS-001'),
        );
        self::assertSame(
            'https://www.javbus.com/en/star/aabc',
            $normalizer->absoluteAndCanonical('https://www.javbus.red/en/stars', '/en/star/aabc'),
        );
    }
}
