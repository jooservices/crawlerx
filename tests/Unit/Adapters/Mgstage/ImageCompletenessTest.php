<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Mgstage;

use JOOservices\CrawlerX\Adapters\Mgstage\Types\Detail;
use JOOservices\CrawlerX\Adapters\Mgstage\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_each_detail_keeps_its_cover_and_every_sample_still_pair(): void
    {
        foreach ([
            ['detail-259luxu-1000.html', 'https://www.mgstage.com/product/product_detail/259LUXU-1000/'],
            ['detail-siro-5767.html', 'https://www.mgstage.com/product/product_detail/SIRO-5767/'],
        ] as [$fixtureName, $url]) {
            $fixture = 'mgstage/' . $fixtureName;
            $page = new Crawler($this->loadFixture($fixture));
            $expectedStills = $page->filter('dl#sample-photo a.sample_image')->count();
            $expectedThumbnails = $page->filter('dl#sample-photo a.sample_image img')->count();
            $movie = $this->item(Detail::class, $fixture, $url, CrawlType::Detail)->meta['movie'];

            self::assertGreaterThan(0, $expectedStills, $fixtureName);
            self::assertNotEmpty($movie['cover_url'], $fixtureName);
            self::assertNotEmpty($movie['metadata']['cover_thumbnail_url'], $fixtureName);
            self::assertGreaterThanOrEqual($expectedStills, count($movie['screenshots']), $fixtureName);
            self::assertGreaterThanOrEqual($expectedThumbnails, count($this->column($movie['screenshots'], 'thumbnail_url')), $fixtureName);
        }
    }

    public function test_listing_keeps_every_card_cover_and_sample_still(): void
    {
        $fixture = 'mgstage/listing-new.html';
        $page = new Crawler($this->loadFixture($fixture));
        $expectedCovers = $page->filter('li.product_list_item')->reduce(
            static fn(Crawler $card): bool => $card->filter('h5 img')->count() > 0,
        )->count();
        $expectedStills = 0;
        $page->filter('li.product_list_item')->each(static function (Crawler $card) use (&$expectedStills): void {
            $expectedStills += $card->filter('.sample_image_btn a[href]')->reduce(
                static fn(Crawler $link): bool => str_contains((string) $link->attr('href'), '/cap_e_'),
            )->count();
        });
        $result = $this->list(
            Listing::class,
            $fixture,
            'https://www.mgstage.com/search/cSearch.php?search_word=&sort=new&list_cnt=30&type=top',
            CrawlType::Listing,
        );
        $movies = $this->metaOf($result->items, 'movie');
        $metadata = array_column($movies, 'metadata');
        $screenshots = array_merge(...array_map(static fn(array $movie): array => $movie['screenshots'], $movies));

        self::assertGreaterThan(0, $expectedCovers);
        self::assertGreaterThanOrEqual($expectedCovers, count($this->column($movies, 'cover_url')));
        self::assertGreaterThanOrEqual($expectedCovers, count($this->column($metadata, 'cover_thumbnail_url')));
        self::assertGreaterThanOrEqual($expectedStills, count($screenshots));
    }
}
