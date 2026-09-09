<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class WarashiAdapterTest extends TestCase
{
    public function test_performer_listing_parses_performer_links_and_last_page(): void
    {
        $client = $this->clientWithFixture('warashi/listing_page1.html');
        $list = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-2-2/female-pornstars/toutes/all/page/1',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertSame('https://warashi-asian-pornstars.fr/en/s-2-2/female-pornstars/toutes/all/page/1', $list->url);
        self::assertSame(1, $list->page);
        self::assertNotEmpty($list->items);
        self::assertMatchesRegularExpression(
            '#^https://warashi-asian-pornstars\.fr/en/s-2-0/[^/]+/asian-female-pornstar/\d+/?$#',
            $list->items[0]->url,
        );
        self::assertNotSame('', (string) $list->items[0]->meta['performer']['external_id']);
        self::assertNotSame('', (string) $list->items[0]->meta['performer']['name']);
        self::assertTrue($list->pagination->hasNextPage);
    }

    public function test_performer_listing_ignores_non_performer_links(): void
    {
        $client = $this->clientWithFixture('warashi/listing_page1.html');
        $list = (new PerformerListing($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-2-2/female-pornstars/toutes/all/page/1',
            type: CrawlType::Listing,
            page: 1,
        ));

        foreach ($list->items as $item) {
            self::assertMatchesRegularExpression(
                '#^https://warashi-asian-pornstars\.fr/en/s-2-0/[^/]+/asian-female-pornstar/\d+/?$#',
                $item->url,
            );
        }
    }

    public function test_performer_detail_parses_full_bio_fields(): void
    {
        $client = $this->clientWithFixture('warashi/detail_yua_mikami.html');
        $item = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-2-0/yua-mikami/asian-female-pornstar/1234',
            type: CrawlType::Detail,
        ));

        self::assertNotSame('', (string) $item->meta['performer']['external_id']);
        self::assertNotSame('', (string) $item->meta['performer']['name']);
        self::assertIsArray($item->meta);
    }

    public function test_performer_detail_handles_missing_bio_fields_as_null(): void
    {
        $client = $this->clientWithFixture('warashi/detail_partial_fields.html');
        $item = (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-2-0/maya-mutsuki/asian-female-pornstar/5678',
            type: CrawlType::Detail,
        ));

        self::assertSame('5678', $item->meta['performer']['external_id']);
        self::assertSame('Maya MUTSUKI', $item->meta['performer']['name']);

        $meta = $item->meta['performer'];
        self::assertNull($meta['metadata']['age'] ?? null);
        self::assertNull($meta['birth_date_raw'] ?? null);
        self::assertSame('2026 - still active', $meta['metadata']['debut_date_raw'] ?? null);
        self::assertNull($meta['metadata']['birthplace'] ?? null);
        self::assertNull($meta['metadata']['zodiac_sign'] ?? null);
        self::assertNull($meta['metadata']['blood_type'] ?? null);
        self::assertNull($meta['size_raw'] ?? null);
        self::assertNull($meta['height_raw'] ?? null);
        self::assertSame('睦月まや', $meta['name_japanese'] ?? null);

        self::assertSame(
            'https://warashi-asian-pornstars.fr/WAPdB-img/pornostars-f/m/a/5678/maya-mutsuki/profil-0/large/wapdb-maya-mutsuki.jpg',
            $meta['profile_image_url'] ?? null,
        );
    }

    public function test_performer_detail_throws_if_name_and_external_id_empty(): void
    {
        $client = $this->clientWithHtml('<html><body><div>No data</div></body></html>');

        $this->expectException(\RuntimeException::class);
        (new PerformerDetail($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-2-0/unknown/asian-female-pornstar/9999',
            type: CrawlType::Detail,
        ));
    }
}
