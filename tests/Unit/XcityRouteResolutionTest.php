<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Xcity\Types\Listing;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerIndex;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerKana;
use JOOservices\CrawlerX\Adapters\Xcity\Types\PerformerListing;
use JOOservices\CrawlerX\Adapters\Xcity\XcityCrawler;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;
use ReflectionClass;
use RuntimeException;

final class XcityRouteResolutionTest extends TestCase
{
    public function test_listing_route_order_resolves_expected_types(): void
    {
        $crawler = new XcityCrawler(new ClientFactory());
        $method = (new ReflectionClass(XcityCrawler::class))->getMethod('resolveListingType');

        self::assertSame(
            Listing::class,
            $method->invoke($crawler, new CrawlRequestDto(url: 'https://xxx.xcity.jp/idol/detail/5517/', type: CrawlType::Listing)),
        );
        self::assertSame(
            PerformerListing::class,
            $method->invoke($crawler, new CrawlRequestDto(url: 'https://xxx.xcity.jp/idol/?ini=a', type: CrawlType::Listing)),
        );
        self::assertSame(
            PerformerKana::class,
            $method->invoke($crawler, new CrawlRequestDto(url: 'https://xxx.xcity.jp/idol/?kana=a', type: CrawlType::Listing)),
        );
        self::assertSame(
            PerformerIndex::class,
            $method->invoke($crawler, new CrawlRequestDto(url: 'https://xxx.xcity.jp/idol/', type: CrawlType::Listing)),
        );
        self::assertSame(
            Listing::class,
            $method->invoke($crawler, new CrawlRequestDto(url: 'https://xxx.xcity.jp/avod/list/', type: CrawlType::Listing)),
        );
    }

    public function test_performer_detail_rejects_discovery_urls(): void
    {
        $crawler = new XcityCrawler(new ClientFactory());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('xcity performer detail requires an /idol/detail/ URL.');

        $crawler->performerDetail(new CrawlRequestDto(
            url: 'https://xxx.xcity.jp/idol/?kana=%E3%81%82',
            type: CrawlType::PerformerDetail,
        ));
    }

    public function test_performer_detail_uses_performer_parser_for_detail_urls(): void
    {
        $crawler = new XcityCrawler(new ClientFactory());
        $method = (new ReflectionClass(XcityCrawler::class))->getMethod('resolveDetailType');

        self::assertSame(
            PerformerDetail::class,
            $method->invoke($crawler, new CrawlRequestDto(url: 'https://xxx.xcity.jp/idol/detail/5517/', type: CrawlType::Detail)),
        );
    }
}
