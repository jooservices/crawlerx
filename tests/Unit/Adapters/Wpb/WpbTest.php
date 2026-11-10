<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Wpb;

use JOOservices\CrawlerX\Adapters\Wpb\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Wpb\Types\Listing;
use JOOservices\CrawlerX\Adapters\Wpb\Urls;
use JOOservices\CrawlerX\Adapters\Wpb\WpbCrawler;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class WpbTest extends TestCase
{
    private const BASE = 'https://wpb.shueisha.co.jp';

    private const GALLERY = self::BASE . '/gravure/20261108-133432/';

    private const COVER = self::BASE . '/gravure/2026/images/02d3b86d4863a35e996596f06c88615d5e15b841.jpg';

    /** @return array<string, mixed> */
    private function gallery(string $url = self::GALLERY): array
    {
        return (new Gallery($this->clientWithFixture('wpb/gallery-20261108-133432.html')))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Gallery))
            ->meta['gallery'];
    }

    public function test_gallery_fields(): void
    {
        $gallery = $this->gallery();

        self::assertSame('20261108-133432', $gallery['external_id']);
        self::assertSame('吉井明子『初恋。』', $gallery['title']);
        self::assertSame('2026-11-08', $gallery['date']);
        self::assertSame(['吉井明子'], $gallery['performers']);
        self::assertSame(['グラビア'], $gallery['categories']);
        self::assertSame(['吉井明子', 'アナウンサー・女子アナ', 'グラビアアイドル', 'デジタル写真集'], $gallery['tags']);
        self::assertSame(1, $gallery['photo_count']);
        self::assertCount(1, $gallery['photos']);
    }

    public function test_gallery_metadata_and_source_fields(): void
    {
        $metadata = $this->gallery()['metadata'];

        self::assertSame('2026-11-08T12:40:00+09:00', $metadata['published_at']);
        self::assertSame('週刊プレイボーイ', $metadata['maker']);
        self::assertSame('前 康輔', $metadata['photographer']);
        self::assertSame(
            [
                ['role' => '撮影', 'name' => '前 康輔'],
                ['role' => 'スタイリング', 'name' => '設楽和代'],
                ['role' => 'ヘア＆メイク', 'name' => '友森理恵'],
            ],
            $metadata['credits'],
        );
        self::assertSame('吉井明子', $metadata['profile']['name']);
        self::assertSame('Akiko YOSHII', $metadata['profile']['name_en']);
        self::assertSame('1980-10-15', $metadata['profile']['birthday']);
        self::assertSame('東京都', $metadata['profile']['birthplace']);
        self::assertSame(157, $metadata['profile']['height_cm']);
        self::assertSame(93, $metadata['profile']['bust']);
        self::assertSame(61, $metadata['profile']['waist']);
        self::assertSame(89, $metadata['profile']['hips']);
        self::assertSame(38, $metadata['attached_dvd_duration_minutes']);
        self::assertSame(self::COVER, $metadata['cover']['url']);
        self::assertNotEmpty($metadata['cover']['thumbnail_url']);
        self::assertStringContainsString('-thumb-', (string) $metadata['cover']['thumbnail_url']);
        self::assertStringStartsWith('■吉井明子', (string) $metadata['description']);
        self::assertNotEmpty($metadata['body']);
        self::assertNotEmpty($metadata['links']);
        self::assertContains(self::BASE . '/search/?keyword=%E5%90%89%E4%BA%95%E6%98%8E%E5%AD%90&tag=1', $metadata['tag_urls']);
        self::assertSame('吉井明子', $metadata['source_fields']['名前']);
        self::assertSame('1980-10-15', $metadata['source_fields']['生年月日']);
        self::assertSame('157', $metadata['source_fields']['身長']);
        self::assertSame('前 康輔', $metadata['source_fields']['撮影']);
        self::assertArrayNotHasKey('runtime', $metadata);
        self::assertArrayNotHasKey('rating', $metadata);
    }

    public function test_gallery_photos_keep_cover_at_full_resolution(): void
    {
        $photo = $this->gallery()['photos'][0];

        self::assertSame(self::COVER, $photo['image_url']);
        self::assertSame(self::COVER, $photo['url']);
        self::assertSame(1, $photo['position']);
        self::assertNotNull($photo['thumbnail_url']);
        self::assertStringContainsString('-thumb-', (string) $photo['thumbnail_url']);
        self::assertSame('cover', $photo['metadata']['kind']);
        self::assertSame('image/jpeg', $photo['metadata']['mime_type']);
        self::assertSame(self::GALLERY, $photo['metadata']['source_page_url']);
        self::assertStringNotContainsString('4375d21660a17e6b0e50d5402d16cb885ab42bd1', $photo['image_url']);
    }

    public function test_listing_cards_and_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('wpb/listing-page-1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/gravure/list/', type: CrawlType::Listing));

        self::assertSame('gallery', $result->entityType);
        self::assertCount(24, $result->items);
        $first = $result->items[0]->meta['gallery'];
        self::assertSame('20261108-133432', $first['external_id']);
        self::assertSame('吉井明子『初恋。』', $first['title']);
        self::assertSame('2026-11-08', $first['date']);
        self::assertSame(self::COVER, $first['metadata']['cover']['url']);
        self::assertNotEmpty($first['metadata']['cover']['thumbnail_url']);
        self::assertSame('gallery', $result->items[0]->nextCrawlType);

        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(157, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/gravure/list/?page=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_url_helpers_and_detection(): void
    {
        self::assertSame('20261108-133432', Urls::externalId(self::GALLERY));
        self::assertSame(self::GALLERY, Urls::gallery(self::GALLERY . '?utm=1'));
        self::assertNull(Urls::externalId(self::BASE . '/gravure/list/'));
        self::assertSame('image/jpeg', Urls::mimeType(self::COVER));

        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('wpb');
        self::assertInstanceOf(WpbCrawler::class, $crawler);

        $gallery = $crawler->detectUrl(self::GALLERY);
        self::assertTrue($gallery->matched);
        self::assertSame(CrawlType::Gallery, $gallery->crawlType);

        $listing = $crawler->detectUrl(self::BASE . '/gravure/list/');
        self::assertTrue($listing->matched);
        self::assertSame(CrawlType::Listing, $listing->crawlType);

        $paged = $crawler->detectUrl(self::BASE . '/gravure/list/?page=3');
        self::assertTrue($paged->matched);
        self::assertSame(CrawlType::Listing, $paged->crawlType);

        self::assertFalse($crawler->detectUrl(self::BASE . '/gravure/news/20261108-133412/')->matched);
        self::assertSame('wpb', $crawler->name());
    }

    public function test_empty_pages_raise_parse_errors(): void
    {
        $this->expectException(CrawlParseException::class);
        (new Listing($this->clientWithHtml('<html><body></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/gravure/list/', type: CrawlType::Listing));
    }

    public function test_empty_gallery_raises_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);
        (new Gallery($this->clientWithHtml('<html><body><h1>x</h1></body></html>')))
            ->execute(new CrawlRequestDto(url: self::GALLERY, type: CrawlType::Gallery));
    }
}
