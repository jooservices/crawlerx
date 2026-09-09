<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests;

use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

abstract class CrawlerXTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FixtureResponder::enableFake();
    }

    protected function tearDown(): void
    {
        FixtureResponder::disableFake();
        parent::tearDown();
    }

    protected function respondWithFixture(string $method, string $url, string $fixturePath, int $status = 200, array $headers = []): void
    {
        FixtureResponder::for($method, $url)->file($fixturePath, $status, $headers);
    }

    protected function crawlHttpClient(): \JOOservices\CrawlerX\Contracts\CrawlHttpClient
    {
        return FixtureResponder::crawlHttpClient();
    }
}
