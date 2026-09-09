<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

final class AbstractHtmlTypeTest extends TestCase
{
    public function test_fetch_crawler_returns_dom_crawler_from_http_response(): void
    {
        $html = '<html><body><a href="/item">Sample</a></body></html>';
        $url = 'https://example.com/list';
        $client = $this->clientWithHtml($html);

        $type = new ExampleHtmlType($client);
        $crawler = $type->fetchForTest(new CrawlRequestDto(url: $url, type: CrawlType::Listing));

        self::assertSame('Sample', trim($crawler->filter('a')->text('')));
    }

    public function test_fetch_crawler_throws_on_empty_html(): void
    {
        $url = 'https://example.com/list';
        $client = $this->clientWithHtml('');

        $type = new ExampleHtmlType($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Example listing page is empty.');

        $type->fetchForTest(new CrawlRequestDto(url: $url, type: CrawlType::Listing));
    }
}

final class ExampleHtmlType extends AbstractHtmlType
{
    public function fetchForTest(CrawlRequestDto $request): Crawler
    {
        return $this->fetchCrawler($request);
    }

    protected function siteLabel(): string
    {
        return 'Example';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }
}
