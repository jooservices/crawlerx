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

    public function test_a_blocked_adapter_request_keeps_the_fetch_attempts_and_names_the_status(): void
    {
        FixtureResponder::for('GET', 'https://www.muramura.tv/movies/103126_1273/')->body($this->moviePage());
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_details/movie_id/103126_1273.json')->body('{}', 403);

        $outcome = CrawlerXFactory::create()->url('https://www.muramura.tv/movies/103126_1273/')->type(CrawlType::Detail)->tryCrawl();

        self::assertFalse($outcome->ok);
        self::assertSame(CrawlErrorCode::Blocked, $outcome->error?->code);
        self::assertStringContainsString('HTTP 403', (string) $outcome->error?->message);
        self::assertNotNull($outcome->error?->fetch);
        self::assertNotSame([], $outcome->error->fetch->attempts);
        self::assertSame('ok', $outcome->error->fetch->attempts[0]['outcome']);
    }

    public function test_a_blocked_adapter_request_with_an_empty_body_says_so(): void
    {
        FixtureResponder::for('GET', 'https://www.muramura.tv/movies/103126_1273/')->body($this->moviePage());
        FixtureResponder::for('GET', 'https://www.muramura.tv/dyn/phpauto/movie_details/movie_id/103126_1273.json')->body('', 200);

        $outcome = CrawlerXFactory::create()->url('https://www.muramura.tv/movies/103126_1273/')->type(CrawlType::Detail)->tryCrawl();

        self::assertSame(CrawlErrorCode::Blocked, $outcome->error?->code);
        self::assertStringContainsString('empty body', (string) $outcome->error?->message);
        self::assertNotNull($outcome->error?->fetch);
    }

    private function moviePage(): string
    {
        return '<html><head><title>Muramura</title></head><body>' . str_repeat('movie ', 400) . '</body></html>';
    }
}
