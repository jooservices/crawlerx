<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Detail;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Listing;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class MinnanoAvAdapterTest extends TestCase
{
    public function test_performer_listing_parses_actress_links_and_pagination(): void
    {
        $client = $this->clientWithFixture('minnanoav/listing_page1.html');
        $list = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://www.minnano-av.com/actress_list.php',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertSame('https://www.minnano-av.com/actress_list.php', $list->url);
        self::assertSame(1, $list->page);
        self::assertNotEmpty($list->items);
        self::assertNotSame('', (string) $list->items[0]->meta['performer']['external_id']);
        self::assertNotSame('', (string) $list->items[0]->meta['performer']['name']);
    }

    public function test_performer_detail_parses_sparse_bio_fields(): void
    {
        $client = $this->clientWithFixture('minnanoav/detail_sparse.html');
        $item = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.minnano-av.com/actress60273.html',
            type: CrawlType::Detail,
        ));

        self::assertSame('60273', $item->meta['performer']['external_id']);
        self::assertSame('来栖唯希', $item->meta['performer']['name']);

        $meta = $item->meta['performer'];
        self::assertSame('2006-01-30', $meta['birth_date_raw'] ?? null);
        self::assertSame('148 cm', $meta['height_raw'] ?? null);
        self::assertSame('T148 / B78(Dカップ) / W / H / S', $meta['size_raw'] ?? null);
        self::assertContains('美少女', $meta['tags'] ?? []);
        self::assertSame('くるすゆき', $meta['name_japanese'] ?? null);
        self::assertSame('Kurusu Yuki', $meta['metadata']['name_romaji'] ?? null);
        self::assertStringContainsString('p_actress_125_125/273/60273.jpg', (string) ($meta['profile_image_url'] ?? ''));
    }

    public function test_performer_detail_parses_rich_bio_fields(): void
    {
        $client = $this->clientWithFixture('minnanoav/detail_rich.html');
        $item = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.minnano-av.com/actress945093.html',
            type: CrawlType::Detail,
        ));

        self::assertSame('945093', $item->meta['performer']['external_id']);
        self::assertNotSame('', (string) $item->meta['performer']['name']);
        self::assertIsArray($item->meta);
    }

    public function test_performer_detail_throws_when_page_has_no_bio(): void
    {
        $client = $this->clientWithHtml('<html><body><h1>Unknown</h1></body></html>');

        $this->expectException(\RuntimeException::class);
        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.minnano-av.com/actress999999.html',
            type: CrawlType::Detail,
        ));
    }

    public function test_filmography_listing_parses_av_links_and_pagination(): void
    {
        $client = $this->clientWithFixture('minnanoav/filmography_page1.html');
        $list = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://www.minnano-av.com/actress.php?actress_id=945093',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertCount(49, $list->items);
        self::assertSame('av570850', $list->items[0]->meta['movie']['external_id']);
        self::assertSame('AV-570850', $list->items[0]->meta['movie']['code'] ?? null);
        self::assertTrue($list->pagination->hasNextPage);
        self::assertSame(2, $list->pagination->nextPage);
    }

    public function test_av_detail_parses_movie_code(): void
    {
        $client = $this->clientWithFixture('minnanoav/av570850.html');
        $item = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://www.minnano-av.com/av570850.html',
            type: CrawlType::Detail,
        ));

        self::assertSame('av570850', $item->meta['movie']['external_id']);
        self::assertSame('AV-570850', $item->meta['movie']['code'] ?? null);
    }

    public function test_performer_detail_includes_filmography_url(): void
    {
        $client = $this->clientWithFixture('minnanoav/detail_sparse.html');
        $item = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://www.minnano-av.com/actress60273.html',
            type: CrawlType::PerformerDetail,
        ));

        self::assertSame(
            'https://www.minnano-av.com/actress.php?actress_id=60273',
            $item->meta['performer']['metadata']['filmography_url'] ?? null,
        );
    }

    /**
     * @param  list<CrawlItemResultDto>  $items
     */
    private function findItem(array $items, string $externalId): ?CrawlItemResultDto
    {
        foreach ($items as $item) {
            if (($item->meta['performer']['external_id'] ?? null) === $externalId) {
                return $item;
            }
        }

        return null;
    }
}
