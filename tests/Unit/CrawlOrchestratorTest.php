<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class CrawlOrchestratorTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_orchestrator_crawls_listing_via_builder(): void
    {
        $url = 'https://onejav.com/new';
        FixtureResponder::for('GET', $url)->file('onejav/listing-page-1.html');

        $orchestrator = CrawlerXFactory::create();
        $result = $orchestrator->url($url)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }

    public function test_orchestrator_crawls_detail_via_builder(): void
    {
        $url = 'https://onejav.com/torrent/ymds282';
        FixtureResponder::for('GET', $url)->file('onejav/detail-sample-1.html');

        $orchestrator = CrawlerXFactory::create();
        $result = $orchestrator->url($url)->type(CrawlType::Detail)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('ymds282', $result->meta['movie']['external_id']);
    }

    public function test_try_crawl_returns_failure_for_unsupported_url(): void
    {
        $orchestrator = CrawlerXFactory::create();
        $outcome = $orchestrator->url('https://example.invalid/unknown')->tryCrawl();

        self::assertFalse($outcome->ok);
        self::assertSame(CrawlErrorCode::UnsupportedUrl, $outcome->error?->code);
    }
}
