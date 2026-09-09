<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;
use PHPUnit\Framework\Attributes\DataProvider;

final class BulkListingCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function onejavDatePagesProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../Fixtures/onejav/date-20260605/page-*.html') ?: [] as $path) {
            if (! is_string($path) || ! preg_match('/page-(\d+)\.html$/', $path, $match)) {
                continue;
            }

            $page = (int) $match[1];
            $url = $page === 1
                ? 'https://onejav.com/2026/06/05'
                : 'https://onejav.com/2026/06/05?page=' . $page;

            yield 'onejav-date-page-' . $page => [$url, 'onejav/date-20260605/page-' . $page . '.html', $page];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ffjavListingPagesProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../Fixtures/ffjav/listing-page-*.html') ?: [] as $path) {
            if (! is_string($path) || ! preg_match('/listing-page-(\d+)\.html$/', $path, $match)) {
                continue;
            }

            $page = (int) $match[1];
            $url = $page === 1
                ? 'https://ffjav.com/javtorrent'
                : 'https://ffjav.com/javtorrent?page=' . $page;

            yield 'ffjav-listing-page-' . $page => [$url, 'ffjav/listing-page-' . $page . '.html'];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function oneFourOneJavListingPagesProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../Fixtures/141jav/listing-page-*.html') ?: [] as $path) {
            if (! is_string($path) || ! preg_match('/listing-page-(\d+)\.html$/', $path, $match)) {
                continue;
            }

            $page = (int) $match[1];
            $url = 'https://www.141jav.com/new?page=' . $page;

            yield '141jav-listing-page-' . $page => [$url, '141jav/listing-page-' . $page . '.html'];
        }
    }

    #[DataProvider('onejavDatePagesProvider')]
    public function test_onejav_date_listing_fixtures_through_facade(string $url, string $fixture, int $page): void
    {
        FixtureResponder::for('GET', $url)->file($fixture);

        $result = CrawlerX::url($url)
            ->site('onejav')
            ->type(CrawlType::Listing)
            ->page($page)
            ->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }

    #[DataProvider('ffjavListingPagesProvider')]
    public function test_ffjav_listing_fixtures_through_facade(string $url, string $fixture): void
    {
        FixtureResponder::for('GET', $url)->file($fixture);

        $result = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }

    #[DataProvider('oneFourOneJavListingPagesProvider')]
    public function test_onefouronejav_listing_fixtures_through_facade(string $url, string $fixture): void
    {
        FixtureResponder::for('GET', $url)->file($fixture);

        $result = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }
}
