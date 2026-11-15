<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Digitalark;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Adapters\Digitalark\DigitalarkCrawler;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class DigitalarkCrawlerTest extends TestCase
{
    private const RELEASE_LIST = 'https://shop.digital-ark.com/view/category/release-date';

    private const ITEM = 'https://shop.digital-ark.com/view/item/000000002230';

    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_listing_request_is_served_by_the_listing_type(): void
    {
        $crawler = $this->respondWith(self::RELEASE_LIST, 'digitalark/listing-page-1.html');

        $result = $crawler->listing(new CrawlRequestDto(url: self::RELEASE_LIST));

        self::assertSame('movie', $result->entityType);
        self::assertCount(50, $result->items);
    }

    public function test_detail_request_is_served_by_the_detail_type(): void
    {
        $crawler = $this->respondWith(self::ITEM, 'digitalark/detail-000000002230.html');

        $result = $crawler->detail(new CrawlRequestDto(url: self::ITEM));

        self::assertSame('000000002230', $result->meta['movie']['external_id']);
    }

    private function respondWith(string $url, string $fixture): DigitalarkCrawler
    {
        ClientBuilder::fake();
        ClientBuilder::respond('GET', $url, (new TestResponseSequence())
            ->push(TestResponse::make(200, ['Content-Type' => 'text/html; charset=utf-8'], $this->loadFixture($fixture))));

        return new DigitalarkCrawler(new ClientFactory());
    }
}
