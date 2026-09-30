<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;

/**
 * Multi-hop discovery: intermediate list items carry a nextCrawlType so the
 * consumer keeps hopping until a terminal (detail) item is reached.
 */
final class SubListingDiscoveryTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_xcity_performer_index_items_are_performer_listing_hops(): void
    {
        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/', 'xcity/performer-index.html');

        $list = CrawlerX::url('https://xxx.xcity.jp/idol/')
            ->site('xcity')
            ->type(CrawlType::PerformerListing)
            ->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $list);
        self::assertSame('performer', $list->entityType);
        self::assertNotEmpty($list->items);
        foreach ($list->items as $item) {
            self::assertStringContainsString('kana=', $item->url);
            self::assertSame('performer_listing', $item->nextCrawlType);
        }
    }

    public function test_xcity_performer_kana_items_are_performer_listing_hops(): void
    {
        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/?kana=%E3%81%82', 'xcity/performer-kana-a.html');

        $list = CrawlerX::url('https://xxx.xcity.jp/idol/?kana=%E3%81%82')
            ->site('xcity')
            ->type(CrawlType::PerformerListing)
            ->crawl();

        self::assertNotEmpty($list->items);
        foreach ($list->items as $item) {
            self::assertStringContainsString('ini=', $item->url);
            self::assertSame('performer_listing', $item->nextCrawlType);
        }
    }

    public function test_xcity_performer_listing_items_are_terminal_detail_urls(): void
    {
        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/?ini=%E3%81%82&num=100', 'xcity/performer-listing-a.html');

        $list = CrawlerX::url('https://xxx.xcity.jp/idol/?ini=%E3%81%82&num=100')
            ->site('xcity')
            ->type(CrawlType::PerformerListing)
            ->crawl();

        self::assertNotEmpty($list->items);
        foreach ($list->items as $item) {
            self::assertStringContainsString('/idol/detail/', $item->url);
            self::assertSame('performer_detail', $item->nextCrawlType);
        }
    }

    public function test_xcity_performer_detail_is_terminal(): void
    {
        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/detail/5517/', 'xcity/performer-detail-5517.html');

        $item = CrawlerX::url('https://xxx.xcity.jp/idol/detail/5517/')
            ->site('xcity')
            ->type(CrawlType::PerformerDetail)
            ->crawl();

        self::assertSame('performer', $item->entityType);
        self::assertNull($item->nextCrawlType);
        self::assertSame('5517', $item->meta['performer']['external_id'] ?? null);
    }

    public function test_jable_performer_listing_parses_model_cards(): void
    {
        $this->respondWithFixture('GET', 'https://en.jable.tv/models/', 'jable/models-listing.html');

        $list = CrawlerX::url('https://en.jable.tv/models/')
            ->site('jable')
            ->type(CrawlType::PerformerListing)
            ->crawl();

        self::assertSame('performer', $list->entityType);
        self::assertNotEmpty($list->items);

        $first = $list->items[0];
        self::assertStringContainsString('/models/', $first->url);
        self::assertSame('performer_detail', $first->nextCrawlType);
        self::assertNotSame('', trim((string) $first->meta['performer']['name'] ?? ''));
    }

    public function test_jable_performer_detail_returns_model(): void
    {
        $slug = 'bfaca44240620be2f3092c294fb22fbe';
        $url = "https://en.jable.tv/models/{$slug}/";
        $this->respondWithFixture('GET', $url, "jable/performer-detail-{$slug}.html");

        $item = CrawlerX::url($url)
            ->site('jable')
            ->type(CrawlType::PerformerDetail)
            ->crawl();

        self::assertSame('performer', $item->entityType);
        self::assertNull($item->nextCrawlType);
        self::assertSame($slug, $item->meta['performer']['external_id'] ?? null);
        self::assertSame('仲間あずみ', $item->meta['performer']['name'] ?? null);
    }
}
