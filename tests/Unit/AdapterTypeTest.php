<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Detail as MinnanoAvDetail;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Listing as MinnanoAvListing;
use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\TagActressListing;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerDetail as XcityPerformerDetail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerIndex as XcityPerformerIndex;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerKana as XcityPerformerKana;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerListing as XcityPerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class AdapterTypeTest extends TestCase
{
    public function test_xcity_performer_discovery_routes_parse_live_captures(): void
    {
        $requests = [
            [new XcityPerformerIndex($this->clientWithFixture('xcity/performer-index.html')), 'https://xxx.xcity.jp/idol/'],
            [new XcityPerformerKana($this->clientWithFixture('xcity/performer-kana-a.html')), 'https://xxx.xcity.jp/idol/?kana=%E3%81%82'],
            [new XcityPerformerListing($this->clientWithFixture('xcity/performer-listing-a.html')), 'https://xxx.xcity.jp/idol/?ini=%E3%81%82&num=100'],
        ];

        foreach ($requests as [$type, $url]) {
            $result = $type->execute(new CrawlRequestDto(url: $url, type: CrawlType::PerformerListing));

            self::assertNotEmpty($result->items);
        }
    }

    public function test_xcity_performer_detail_parses_live_capture(): void
    {
        $result = (new XcityPerformerDetail($this->clientWithFixture('xcity/performer-detail-5517.html')))->execute(
            new CrawlRequestDto(url: 'https://xxx.xcity.jp/idol/detail/5517/', type: CrawlType::PerformerDetail),
        );

        $performer = $result->meta['performer'] ?? null;
        self::assertIsArray($performer);
        self::assertIsString($performer['name'] ?? null);
        self::assertNotSame('', trim($performer['name']));
    }

    public function test_minnano_av_movie_routes_parse_live_captures(): void
    {
        $listing = (new MinnanoAvListing($this->clientWithFixture('minnanoav/movie-listing.html')))->execute(
            new CrawlRequestDto(url: 'https://www.minnano-av.com/actress.php?actress_id=945093', type: CrawlType::Listing),
        );
        $detail = (new MinnanoAvDetail($this->clientWithFixture('minnanoav/movie-detail.html')))->execute(
            new CrawlRequestDto(url: 'https://www.minnano-av.com/av159081.html', type: CrawlType::Detail),
        );

        self::assertNotEmpty($listing->items);
        $movie = $detail->meta['movie'] ?? null;
        self::assertIsArray($movie);
        self::assertIsString($movie['external_id'] ?? null);
        self::assertNotSame('', trim($movie['external_id']));
    }

    public function test_onejav_tag_route_parses_live_capture(): void
    {
        $result = (new TagActressListing($this->clientWithFixture('onejav/tag-fc2.html')))->execute(
            new CrawlRequestDto(url: 'https://onejav.com/tag/FC2', type: CrawlType::Listing),
        );

        self::assertNotEmpty($result->items);
    }
}
