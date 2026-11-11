<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Gravidia;

use JOOservices\CrawlerX\Adapters\Gravidia\GravidiaCrawler;
use JOOservices\CrawlerX\Adapters\Gravidia\Types\Detail;
use JOOservices\CrawlerX\Adapters\Gravidia\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class GravidiaTest extends TestCase
{
    private const BASE = 'https://gravidia.jp';

    public function test_listing_extracts_every_card_field_and_pagination_from_page_one(): void
    {
        $result = (new Listing($this->clientWithFixture('gravidia/listing-page-1.html')))->execute(new CrawlRequestDto(
            url: self::BASE . '/search/?post_type=product&cts=cts&s=',
        ));

        self::assertSame('movie', $result->entityType);
        self::assertCount(16, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('48277', $movie['external_id']);
        self::assertSame('48277', $movie['code']);
        self::assertSame('穂波あみ AMI-ABILITY', $movie['title']);
        self::assertSame(self::BASE . '/product/48277', $result->items[0]->url);
        self::assertSame('https://gravidia.jp/wp-content/uploads/2026/10/09D183600000d0000000.jpg', $movie['cover_url']);
        self::assertSame('穂波あみ', $movie['performers'][0]['name']);
        self::assertSame('ＬＵＣＫＭＡＮ', $movie['metadata']['photographer']);
        self::assertSame('スピ/サン', $movie['metadata']['label']);
        self::assertSame(['スピ/サン'], $movie['metadata']['labels']);
        self::assertSame('Gravidia', $movie['metadata']['maker']);

        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(237, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/search/page/2?post_type=product&cts=cts&s', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_page_two_uses_active_page_and_site_pagination_markup(): void
    {
        $result = (new Listing($this->clientWithFixture('gravidia/listing-page-2.html')))->execute(new CrawlRequestDto(
            url: self::BASE . '/search/page/2?post_type=product&cts=cts&s=',
            page: 2,
        ));

        self::assertCount(16, $result->items);
        self::assertSame('47226', $result->items[0]->meta['movie']['external_id']);
        self::assertSame('【デジタル限定豪華版30ページ増】堀未央奈写真集 深紅の余韻', $result->items[0]->meta['movie']['title']);
        self::assertSame('https://gravidia.jp/wp-content/uploads/2026/10/096825260000d0000000_l.jpg', $result->items[0]->meta['movie']['cover_url']);
        self::assertSame('堀 未央奈', $result->items[0]->meta['movie']['performers'][0]['name']);
        self::assertSame('藤原 宏', $result->items[0]->meta['movie']['metadata']['photographer']);
        self::assertSame('その他', $result->items[0]->meta['movie']['metadata']['label']);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(237, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/search/page/3?post_type=product&cts=cts&s', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_detail_extracts_every_public_field_and_only_the_work_image(): void
    {
        $result = (new Detail($this->clientWithFixture('gravidia/detail-48277.html')))->execute(new CrawlRequestDto(
            url: self::BASE . '/product/48277',
        ));

        self::assertSame(self::BASE . '/product/48277', $result->url);
        self::assertSame('movie', $result->entityType);
        $movie = $result->meta['movie'];
        self::assertSame('48277', $movie['external_id']);
        self::assertSame('48277', $movie['code']);
        self::assertSame('穂波あみ AMI-ABILITY', $movie['title']);
        self::assertSame('https://gravidia.jp/wp-content/uploads/2026/10/09D183600000d0000000.jpg', $movie['cover_url']);
        self::assertSame('漫画家とコスプレイヤー、“才色兼備の二刀流”で話題沸騰の穂波あみ。太陽照り付けるプールサイドと自然に囲まれた海辺の別荘を舞台に、カルチャー系女子の自然体な笑顔から、艶やかな美ボディーまで、意外性たっぷりのカットが盛りだくさん！ 【2026年スピリッツ50号】掲載のグラビア未公開写真を再構成した贅沢フォトブック！', $movie['description']);
        self::assertSame('2026-11-09', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame('穂波あみ', $movie['performers'][0]['name']);
        self::assertSame(['スピ/サン', 'コスプレイヤー', 'その他', 'SEXY', 'カワイイ', 'スレンダー', '水着', 'プール', '屋外'], $movie['tags']);
        self::assertCount(1, $movie['screenshots']);
        self::assertSame('https://gravidia.jp/wp-content/uploads/2026/10/09D183600000d0000000.jpg', $movie['screenshots'][0]['url']);
        self::assertNull($movie['screenshots'][0]['thumbnail_url']);

        $metadata = $movie['metadata'];
        self::assertSame('Gravidia', $metadata['maker']);
        self::assertSame('48277', $metadata['product_id']);
        self::assertSame('穂波あみ', $metadata['model']);
        self::assertSame('https://gravidia.jp/model/2172/', $metadata['model_url']);
        self::assertSame('ＬＵＣＫＭＡＮ', $metadata['photographer']);
        self::assertSame('スピ/サン', $metadata['label']);
        self::assertSame('スピ/サン グラビアフォトブック', $metadata['series']);
        self::assertSame('2026/11/09', $metadata['release_date_raw']);
        self::assertSame('2026-11-09', $metadata['posted_date']);
        self::assertSame('2026-11-08T21:00:00+00:00', $metadata['published_at']);
        self::assertSame('1,100円(税込)', $metadata['price']);
        self::assertSame('https://gravidia.tameshiyo.me/09D183600000D0000000', $metadata['preview_url']);
        self::assertSame([
            '発売日' => '2026/11/09',
            'シリーズ' => 'スピ/サン グラビアフォトブック',
            '撮影' => 'ＬＵＣＫＭＡＮ',
        ], $metadata['source_fields']);
    }

    public function test_url_detection_matches_only_captured_listing_and_detail_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('gravidia');
        self::assertInstanceOf(GravidiaCrawler::class, $crawler);

        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/search/?post_type=product&cts=cts&s=')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/search/page/2?post_type=product&cts=cts&s=')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/product/48277')->crawlType);
        self::assertFalse($crawler->detectUrl(self::BASE . '/ranking')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/search/page/0?post_type=product&cts=cts&s=')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/search/?post_type=member')->matched);
        self::assertSame('gravidia', $crawler->name());
    }

    public function test_listing_rejects_a_page_without_product_cards(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><main>nothing</main></body></html>')))->execute(new CrawlRequestDto(
            url: self::BASE . '/search/?post_type=product&cts=cts&s=',
        ));
    }
}
