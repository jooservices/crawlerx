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
        $movie = $result->items[0]->meta['movie'] ?? null;
        self::assertIsArray($movie);
        self::assertIsString($movie['external_id'] ?? null);
        self::assertNotSame('', trim($movie['external_id']));
    }

    public function test_detail_parses_metadata_from_fixture(): void
    {
        $client = $this->clientWithFixture('missav/detail-fns-247.html');

        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://missav.ws/en/fns-247',
            type: CrawlType::Detail,
        ));

        $movie = $result->meta['movie'] ?? null;
        self::assertIsArray($movie);
        self::assertSame('fns-247', $movie['external_id'] ?? null);
        self::assertIsString($movie['title'] ?? null);
        self::assertStringStartsWith('FNS-247', $movie['title']);
        self::assertSame('FNS-247', $movie['code'] ?? null);
        self::assertNotNull($movie['cover_url'] ?? null);
        self::assertSame('2026-08-29', $movie['date'] ?? null);
        self::assertSame(172, $movie['duration'] ?? null);
        self::assertIsArray($movie['performers'] ?? null);
        self::assertContains('Tsubasa Mai', array_column($movie['performers'], 'name'));
    }

    public function test_listing_fails_on_cloudflare_challenge(): void
    {
        $client = $this->clientWithHtml(
            '<html><title>Just a moment...</title><body>challenges.cloudflare.com</body></html>',
            403,
            ['cf-mitigated' => 'challenge'],
        );

        $this->expectException(CrawlBlockedException::class);
        $this->expectExceptionMessage('MissAV listing page is blocked or unavailable.');

        (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://missav.to/latest-updates',
            type: CrawlType::Listing,
        ));
    }
}
