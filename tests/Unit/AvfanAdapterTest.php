<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Avfan\Types\Detail;
use JOOservices\CrawlerX\Adapters\Avfan\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class AvfanAdapterTest extends TestCase
{
    public function test_listing_parses_movie_links_from_real_fixture(): void
    {
        $client = $this->clientWithFixture('Avfan/listing-placeholder.html');
        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://avfan.com/en',
            type: CrawlType::Listing,
            page: 1,
        ));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
        self::assertMatchesRegularExpression('#^https://avfan\.com/en/movies/#', $result->items[0]->url);
    }

    public function test_detail_parses_core_fields_from_real_fixture(): void
    {
        $client = $this->clientWithFixture('Avfan/detail-ewTlM3Fj.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://avfan.com/en/movies/ewTlM3Fj',
            type: CrawlType::Detail,
        ));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('ewTlM3Fj', $result->meta['movie']['external_id']);
        self::assertNotSame('', trim((string) $result->meta['movie']['title']));
        self::assertNotSame('', (string) ($result->meta['movie']['code'] ?? ''));
    }
}
