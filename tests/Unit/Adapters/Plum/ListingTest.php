<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Plum;

use JOOservices\CrawlerX\Adapters\Plum\PlumCrawler;
use JOOservices\CrawlerX\Adapters\Plum\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class ListingTest extends TestCase
{
    private const SERIES = 'https://www.plum-web.com/series/puw/';

    private const HOME = 'https://www.plum-web.com/';

    private function listing(string $fixture, string $url): \JOOservices\CrawlerX\Dto\CrawlListResultDto
    {
        return (new Listing($this->clientWithFixture($fixture)))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing));
    }

    public function test_series_card_fields(): void
    {
        $result = $this->listing('plum/listing-series-puw.html', self::SERIES);
        $card = $result->items[0];
        $movie = $card->meta['movie'];

        self::assertCount(20, $result->items);
        self::assertSame('movie', $result->entityType);
        self::assertSame('https://www.plum-web.com/title/puw070/', $card->url);
        self::assertSame('puw070', $movie['external_id']);
        self::assertSame('PUW-070', $movie['code']);
        self::assertSame('ごく普通の主婦かと甘く見てたら実はかなり下品で卑猥だった８人', $movie['title']);
        self::assertSame('https://www.plum-web.com/wp-content/uploads/package/PUW-070/puw070.jpg', $movie['cover_url']);
        self::assertSame(
            ['村上涼子', '高瀬杏', '松下美雪', '山口翠', '三上絵理香', '美浜かおり', '仁美麗華', '成宮いろは'],
            array_column($movie['performers'], 'name'),
        );
        self::assertNull($movie['date']);
    }

    public function test_series_pagination(): void
    {
        $pagination = $this->listing('plum/listing-series-puw.html', self::SERIES)->pagination;

        self::assertSame(1, $pagination->currentPage);
        self::assertSame(5, $pagination->lastPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertSame('https://www.plum-web.com/series/puw/page/2/', $pagination->nextUrl);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_home_cards_carry_the_release_date_and_the_code_from_the_cover(): void
    {
        $result = $this->listing('plum/listing-home.html', self::HOME);
        $movie = $result->items[0]->meta['movie'];

        self::assertCount(26, $result->items);
        self::assertSame('hz015', $movie['external_id']);
        self::assertSame('2026-11-05', $movie['date']);
        self::assertSame('HZ-015', $movie['code']);
        self::assertSame('整体師さん、整体師さん! お前のカラダは犯罪だ！ぷるぷる Fカップ 88cm ど…', $movie['title']);
        self::assertSame(['凪宮ゆきの'], array_column($movie['performers'], 'name'));
        self::assertSame('https://www.plum-web.com/wp-content/uploads/package/HZ-015/hz015.jpg', $movie['cover_url']);
    }

    public function test_home_has_no_next_page_and_no_ranking_noise(): void
    {
        $result = $this->listing('plum/listing-home.html', self::HOME);

        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(count($result->items), count(array_unique(array_map(static fn($item): string => $item->url, $result->items))));
        self::assertNotContains('https://www.plum-web.com/title/spe004/', array_map(static fn($item): string => $item->url, $result->items));
    }

    public function test_a_page_without_cards_is_a_parse_failure(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><section class="content_wrap"></section></body></html>')))
            ->execute(new CrawlRequestDto(url: self::SERIES, type: CrawlType::Listing));
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('plum');
        self::assertInstanceOf(PlumCrawler::class, $crawler);
        $expected = [
            '/' => CrawlType::Listing,
            '/series/puw/' => CrawlType::Listing,
            '/series/puw/page/2/' => CrawlType::Listing,
            '/category/wife' => CrawlType::Listing,
            '/ym/202611' => CrawlType::Listing,
            '/page/2/' => CrawlType::Listing,
            '/title/puw070/' => CrawlType::Detail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl('https://www.plum-web.com' . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl('https://www.plum-web.com/genrelist/')->matched);
        self::assertSame('plum', $crawler->name());
    }
}
