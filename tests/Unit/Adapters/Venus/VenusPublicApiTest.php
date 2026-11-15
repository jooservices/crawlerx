<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Venus;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Tests\TestCase;
use Nyholm\Psr7\Response;

final class VenusPublicApiTest extends TestCase
{
    private const BASE = 'https://venus-av.com';

    private const DETAIL_URL = 'https://venus-av.com/products/%e3%80%8c%e3%81%8a%e3%81%b0%e3%81%95%e3%82%93%e3%81%ae%e4%b8%8b%e7%9d%80%e3%81%a7%e8%88%88%e5%a5%ae%e3%81%99%e3%82%8b%e3%81%ae%ef%bc%9f%e3%80%8d%e8%84%b1%e3%81%8e%e3%81%9f%e3%81%a6%e3%81%ae-44/';

    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_public_url_api_crawls_fixture_backed_listing_and_detail(): void
    {
        $registry = ClientBuilder::fake();
        $registry->respond('GET', self::BASE . '/all', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $this->loadFixture('venus/listing-page-1.html')),
        ));
        $registry->respond('GET', self::BASE . '/products/*', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $this->loadFixture('venus/detail-VENX-388.html')),
        ));

        $listing = CrawlerX::url(self::BASE . '/all')->crawl();
        $detail = CrawlerX::url(self::DETAIL_URL)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $listing);
        self::assertSame(20, count($listing->items));
        self::assertSame('VEQ-289', $listing->items[0]->meta['movie']['code']);
        self::assertSame(172, $listing->pagination->lastPage);

        self::assertInstanceOf(CrawlItemResultDto::class, $detail);
        $movie = $detail->meta['movie'];
        self::assertSame('VENX-388', $movie['code']);
        self::assertSame('INCEST', $movie['metadata']['label']);
        self::assertSame('VENX-388', $movie['metadata']['source_fields']['品番・分数']);
        self::assertSame('https://venus-av.com/wp-content/uploads/old/VENX-388.jpg', $movie['cover_url']);
        self::assertCount(0, $movie['screenshots']);
    }

    public function test_public_url_api_uses_highest_visible_page_when_last_link_is_omitted(): void
    {
        $html = preg_replace(
            '#<div class=.wp-pagenavi. role=.navigation.>.*?</div>#s',
            '<div class="wp-pagenavi" role="navigation">'
                . '<span class="current">171</span>'
                . '<a class="page larger" href="https://venus-av.com/all/page/172/">172</a>'
                . '<a class="nextpostslink" href="https://venus-av.com/all/page/172/">»</a>'
                . '</div>',
            $this->loadFixture('venus/listing-page-2.html'),
        );
        self::assertIsString($html);

        ClientBuilder::fake()->respond('GET', self::BASE . '/all/page/171/', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html),
        ));

        $result = CrawlerX::url(self::BASE . '/all/page/171/')->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame(171, $result->pagination->currentPage);
        self::assertSame(172, $result->pagination->lastPage);
        self::assertSame(172, $result->pagination->nextPage);
        self::assertTrue($result->pagination->hasNextPage);
    }
}
