<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Asagei;

use JOOservices\CrawlerX\Adapters\Asagei\AsageiCrawler;
use JOOservices\CrawlerX\Adapters\Asagei\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Asagei\Types\Listing;
use JOOservices\CrawlerX\Adapters\Asagei\Urls;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class AsageiTest extends TestCase
{
    private const BASE = 'https://www.asagei.com';

    private const LISTING = self::BASE . '/tag/%E3%82%B0%E3%83%A9%E3%83%93%E3%82%A2';

    private const ARTICLE = self::BASE . '/excerpt/349499';

    private const COVER = 'https://cdn.asagei.com/asagei/uploads/2026/09/ap-aoyama-20260914151510.jpg';

    private const COVER_THUMB = 'https://cdn.asagei.com/asagei/uploads/2026/09/ap-aoyama-20260914151510-150x150.jpg';

    /** @return array<string, mixed> */
    private function gallery(string $url = self::ARTICLE): array
    {
        return (new Gallery($this->clientWithFixture('asagei/gallery-349499.html')))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Gallery))
            ->meta['gallery'];
    }

    public function test_gallery_fields(): void
    {
        $gallery = $this->gallery();

        self::assertSame('349499', $gallery['external_id']);
        self::assertSame(
            '【撮りおろしインタビュー】青山天南が極める「令和の尻職人」の道…オフ会は「お値段以上」の満足感を！',
            $gallery['title'],
        );
        self::assertSame('2026-09-17', $gallery['date']);
        self::assertSame(['芸能'], $gallery['categories']);
        self::assertSame(['グラビア', 'アイドル'], $gallery['tags']);
        self::assertSame(1, $gallery['photo_count']);
        self::assertCount(1, $gallery['photos']);
    }

    public function test_gallery_metadata_and_source_fields(): void
    {
        $metadata = $this->gallery()['metadata'];

        self::assertSame('2026-09-17T16:00:00+09:00', $metadata['published_at']);
        self::assertSame(self::BASE . '/category/geinou', $metadata['category_urls'][0]);
        self::assertSame(self::BASE . '/tag/グラビア', $metadata['tag_urls'][0]);
        self::assertSame(self::BASE . '/tag/アイドル', $metadata['tag_urls'][1]);
        self::assertSame(self::COVER, $metadata['cover']['url']);
        self::assertSame('image/jpeg', $metadata['cover']['mime_type']);
        self::assertSame(self::BASE . '/excerpt/349581', $metadata['prev_url']);
        self::assertSame(self::BASE . '/excerpt/349511', $metadata['next_url']);
        self::assertStringContainsString('ミスAsageiSECRET2026', (string) $metadata['description']);
        self::assertNotEmpty($metadata['body']);
        self::assertStringContainsString('青山天南', $metadata['body'][0]);
        self::assertSame('2026年09月17日 16:00', $metadata['source_fields']['Posted on']);
        self::assertSame('芸能', $metadata['source_fields']['カテゴリー']);
        self::assertSame('グラビア, アイドル', $metadata['source_fields']['タグ']);
    }

    public function test_gallery_photos_keep_order_and_page_images(): void
    {
        $photos = $this->gallery()['photos'];

        self::assertSame(self::COVER, $photos[0]['image_url']);
        self::assertSame(self::COVER, $photos[0]['url']);
        self::assertSame(1, $photos[0]['position']);
        self::assertNull($photos[0]['thumbnail_url']);
        self::assertSame(self::ARTICLE, $photos[0]['metadata']['source_page_url']);
        self::assertSame('image/jpeg', $photos[0]['metadata']['mime_type']);
    }

    public function test_gallery_excludes_related_and_chrome_images(): void
    {
        $urls = implode(' ', array_column($this->gallery()['photos'], 'image_url'));

        self::assertStringNotContainsString('/asagei/assets/', $urls);
        self::assertStringNotContainsString('ap-kanzake', $urls);
        self::assertStringNotContainsString('ap-hosono', $urls);
        self::assertStringNotContainsString('-150x150', $urls);
    }

    public function test_gallery_rejects_empty_article(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Gallery($this->clientWithHtml('<html><body><h1 class="entry-title">x</h1><div id="entry-content"></div></body></html>')))
            ->execute(new CrawlRequestDto(url: self::ARTICLE, type: CrawlType::Gallery));
    }

    public function test_listing_fields(): void
    {
        $result = (new Listing($this->clientWithFixture('asagei/listing-gravure-page1.html')))
            ->execute(new CrawlRequestDto(url: self::LISTING, type: CrawlType::Listing));
        $first = $result->items[0]->meta['gallery'];

        self::assertSame('gallery', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertGreaterThanOrEqual(10, count($result->items));
        self::assertSame(self::ARTICLE, $result->items[0]->url);
        self::assertSame('349499', $first['external_id']);
        self::assertSame(
            '【撮りおろしインタビュー】青山天南が極める「令和の尻職人」の道…オフ会は「お値段以上」の満足感を！',
            $first['title'],
        );
        self::assertSame('2026-09-17', $first['date']);
        self::assertSame(['芸能'], $first['categories']);
        self::assertSame(['グラビア', 'アイドル'], $first['tags']);
        self::assertSame('グラビア', $first['metadata']['listing_label']);
        self::assertSame(self::COVER, $first['metadata']['cover']['url']);
        self::assertSame(self::COVER_THUMB, $first['metadata']['cover']['thumbnail_url']);
        self::assertStringContainsString('ミスAsageiSECRET2026', (string) $first['metadata']['excerpt']);
        self::assertSame('2026年09月17日 16:00', $first['metadata']['source_fields']['Posted on']);
        self::assertSame('gallery', $result->items[0]->nextCrawlType);
    }

    public function test_listing_pagination(): void
    {
        $pagination = (new Listing($this->clientWithFixture('asagei/listing-gravure-page1.html')))
            ->execute(new CrawlRequestDto(url: self::LISTING, type: CrawlType::Listing))
            ->pagination;

        self::assertSame(1, $pagination->currentPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertSame(59, $pagination->lastPage);
        self::assertTrue($pagination->hasNextPage);
        self::assertSame(self::LISTING . '?page=2', $pagination->nextUrl);
    }

    public function test_listing_rejects_empty_page(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><main class="main-area"><div class="content-area"></div></main></body></html>')))
            ->execute(new CrawlRequestDto(url: self::LISTING, type: CrawlType::Listing));
    }

    public function test_url_helpers_and_detection(): void
    {
        self::assertSame('349499', Urls::externalId(self::ARTICLE));
        self::assertSame(self::ARTICLE, Urls::article(self::ARTICLE . '?page_type=show'));
        self::assertSame(self::COVER, Urls::original(self::COVER_THUMB));
        self::assertNull(Urls::externalId(self::LISTING));
        self::assertSame('image/jpeg', Urls::mimeType(self::COVER));
        self::assertNull(Urls::mimeType('https://www.asagei.com/x.bin'));

        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('asagei');
        self::assertInstanceOf(AsageiCrawler::class, $crawler);
        self::assertSame('asagei', $crawler->name());

        $expected = [
            '/' => CrawlType::Listing,
            '/tag/グラビア' => CrawlType::Listing,
            '/tag/グラビア?page=2' => CrawlType::Listing,
            '/category/geinou' => CrawlType::Listing,
            '/excerpt/349499' => CrawlType::Gallery,
            '/excerpt/349499?page_type=show' => CrawlType::Gallery,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/adinfo')->matched);
    }
}
