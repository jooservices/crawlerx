<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Missav\Types\Detail;
use JOOservices\CrawlerX\Adapters\Missav\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Tests\TestCase;

final class MissavAdapterTest extends TestCase
{
    public function test_listing_parses_live_capture(): void
    {
        $client = $this->clientWithFixture('missav/listing-new.html');

        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://missav.ws/en/new',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertNotEmpty($result->items);
        self::assertSame('fns-247', $result->items[0]->meta['movie']['external_id']);
    }

    public function test_detail_parses_metadata_from_fixture(): void
    {
        $client = $this->clientWithFixture('missav/detail-fns-247.html');

        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://missav.ws/en/fns-247',
            type: CrawlType::Detail,
        ));

        self::assertSame('fns-247', $result->meta['movie']['external_id']);
        self::assertStringStartsWith('FNS-247', (string) $result->meta['movie']['title']);
        self::assertSame('FNS-247', $result->meta['movie']['code']);
        self::assertNotNull($result->meta['movie']['cover_url']);
        self::assertSame('2026-08-29', $result->meta['movie']['date']);
        self::assertSame(172, $result->meta['movie']['duration']);
        self::assertContains('Tsubasa Mai', array_column($result->meta['movie']['performers'], 'name'));
    }

    public function test_listing_fails_on_cloudflare_challenge(): void
    {
        $client = $this->clientWithFixture('missav/cloudflare.html', 403, ['cf-mitigated' => 'challenge']);

        $this->expectException(CrawlBlockedException::class);
        $this->expectExceptionMessage('MissAV listing page is blocked or unavailable.');

        (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://missav.to/latest-updates',
            type: CrawlType::Listing,
        ));
    }
}
