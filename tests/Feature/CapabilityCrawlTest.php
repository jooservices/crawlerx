<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;
use PHPUnit\Framework\Attributes\DataProvider;

final class CapabilityCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, CrawlType, string}>
     */
    public static function capabilitySamples(): iterable
    {
        yield 'xcity performer index' => [
            'xcity',
            'https://xxx.xcity.jp/idol/',
            CrawlType::PerformerListing,
            'xcity/performer-index.html',
        ];
        yield 'xcity performer detail' => [
            'xcity',
            'https://xxx.xcity.jp/idol/detail/5517/',
            CrawlType::PerformerDetail,
            'xcity/performer-detail-5517.html',
        ];
        yield 'minnano movie listing' => [
            'minnanoav',
            'https://www.minnano-av.com/actress.php?actress_id=945093',
            CrawlType::Listing,
            'minnanoav/movie-listing.html',
        ];
        yield 'minnano movie detail' => [
            'minnanoav',
            'https://www.minnano-av.com/av159081.html',
            CrawlType::Detail,
            'minnanoav/movie-detail.html',
        ];
        yield 'warashi performer listing' => [
            'warashi',
            'https://warashi-asian-pornstars.fr/en/s-2-2/female-pornstars/toutes/all/page/1',
            CrawlType::PerformerListing,
            'warashi/listing_page1.html',
        ];
        yield 'warashi performer detail' => [
            'warashi',
            'https://warashi-asian-pornstars.fr/en/s-2-0/yua-mikami/asian-female-pornstar/1234',
            CrawlType::PerformerDetail,
            'warashi/detail_yua_mikami.html',
        ];
        yield 'javdatabase performer listing' => [
            'javdatabase',
            'https://www.javdatabase.com/idols/',
            CrawlType::PerformerListing,
            'javdatabase/listing_page1.html',
        ];
        yield 'javdatabase performer detail' => [
            'javdatabase',
            'https://www.javdatabase.com/idols/yua-mikami/',
            CrawlType::PerformerDetail,
            'javdatabase/detail_yua_mikami.html',
        ];
        yield 'eporner gallery' => [
            'eporner',
            'https://www.eporner.com/gallery/xKeoFe7VHmO/Iori-Kogawa-gu-chuaniori-STAR-836-Uncensored-Leak/',
            CrawlType::Gallery,
            'eporner/gallery-sample.html',
        ];
    }

    #[DataProvider('capabilitySamples')]
    public function test_explicit_site_capability_crawls_live_fixture(
        string $site,
        string $url,
        CrawlType $type,
        string $fixture,
    ): void {
        FixtureResponder::for('GET', $url)->file($fixture);

        $result = CrawlerX::site($site)->url($url)->type($type)->crawl();

        if ($type === CrawlType::Listing || $type === CrawlType::PerformerListing) {
            self::assertInstanceOf(CrawlListResultDto::class, $result);
            self::assertNotEmpty($result->items);

            return;
        }

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        $entity = $result->meta[$result->entityType] ?? null;
        self::assertIsArray($entity);
        self::assertNotSame('', trim((string) ($entity['title'] ?? $entity['name'] ?? '')));
    }
}
