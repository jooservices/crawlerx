<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Gravurech;

use JOOservices\CrawlerX\Adapters\Gravurech\GravurechCrawler;
use JOOservices\CrawlerX\Adapters\Gravurech\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Gravurech\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class GravurechTest extends TestCase
{
    private const BASE = 'https://gravure-ch.com';

    public function test_gallery_extracts_every_field_from_real_capture(): void
    {
        $result = (new Gallery($this->clientWithFixture('gravurech/detail-j6R.html')))->execute(new CrawlRequestDto(url: self::BASE . '/content/j6R'));

        self::assertSame(self::BASE . '/content/j6R', $result->url);
        self::assertSame('gallery', $result->entityType);
        $gallery = $result->meta['gallery'];
        self::assertSame('j6R', $gallery['external_id']);
        self::assertSame('澄田綾乃 002', $gallery['title']);
        self::assertSame(3, $gallery['photo_count']);
        self::assertSame('2025-07-15', $gallery['date']);
        self::assertSame(['澄田綾乃'], $gallery['performers']);
        self::assertSame(['フォト'], $gallery['categories']);
        self::assertSame(['水着', 'グラドル'], $gallery['tags']);

        $metadata = $gallery['metadata'];
        self::assertSame('1713', $metadata['content_id']);
        self::assertSame('漫画アクション2025年7月15日号掲載', $metadata['description']);
        self::assertSame('漫画アクション', $metadata['magazine']);
        self::assertSame([self::BASE . '/search/cast/170'], $metadata['performer_urls']);
        self::assertSame(['170'], $metadata['performer_ids']);
        self::assertSame([self::BASE . '/search/tag/3', self::BASE . '/search/tag/9'], $metadata['tag_urls']);
        self::assertSame(['3', '9'], $metadata['tag_ids']);
        self::assertSame('撮り下ろしフォト', $metadata['category_label']);
        self::assertSame('澄田綾乃', $metadata['cast_label']);
        self::assertSame('水着', $metadata['costume']);
        self::assertSame('グラドル', $metadata['job']);
        self::assertSame('regular-member-content', $metadata['content_class']);
        self::assertSame(['ページ数' => '3'], $metadata['source_fields']);
    }

    public function test_gallery_returns_only_the_public_cover_and_no_related_covers(): void
    {
        $result = (new Gallery($this->clientWithFixture('gravurech/detail-j6R.html')))->execute(new CrawlRequestDto(url: self::BASE . '/content/j6R'));

        $photos = $result->meta['gallery']['photos'];
        self::assertCount(1, $photos);
        self::assertSame('1713', $photos[0]['id']);
        self::assertSame(self::BASE . '/storage/newPhoto/1713_thumbnail1.jpg', $photos[0]['image_url']);
        self::assertSame(self::BASE . '/storage/newPhoto/1713_thumbnail1.jpg', $photos[0]['url']);
        self::assertSame(self::BASE . '/storage/newPhoto/1713_thumbnail1.jpg', $photos[0]['thumbnail_url']);
        self::assertSame(1, $photos[0]['position']);
    }

    public function test_second_gallery_capture_has_its_own_values(): void
    {
        $result = (new Gallery($this->clientWithFixture('gravurech/detail-0bb.html')))->execute(new CrawlRequestDto(url: self::BASE . '/content/0bb'));

        $gallery = $result->meta['gallery'];
        self::assertSame('0bb', $gallery['external_id']);
        self::assertSame('東雲うみ 004', $gallery['title']);
        self::assertSame(8, $gallery['photo_count']);
        self::assertSame('2025-07-01', $gallery['date']);
        self::assertSame(['東雲うみ'], $gallery['performers']);
        self::assertSame(['64'], $gallery['metadata']['performer_ids']);
        self::assertSame(self::BASE . '/storage/newPhoto/1710_thumbnail1.jpg', $gallery['photos'][0]['image_url']);
        self::assertSame(['ページ数' => '8'], $gallery['metadata']['source_fields']);
    }

    public function test_gallery_without_a_parsable_issue_date_keeps_the_description(): void
    {
        $html = str_replace('漫画アクション2025年7月15日号掲載', '漫画アクション2025年2月31日号掲載', $this->loadFixture('gravurech/detail-j6R.html'));
        $result = (new Gallery($this->clientWithHtml($html)))->execute(new CrawlRequestDto(url: self::BASE . '/content/j6R'));

        $gallery = $result->meta['gallery'];
        self::assertNull($gallery['date']);
        self::assertNull($gallery['metadata']['magazine']);
        self::assertSame('漫画アクション2025年2月31日号掲載', $gallery['metadata']['description']);

        $html = str_replace('漫画アクション2025年7月15日号掲載', '特別企画', $this->loadFixture('gravurech/detail-j6R.html'));
        $result = (new Gallery($this->clientWithHtml($html)))->execute(new CrawlRequestDto(url: self::BASE . '/content/j6R'));
        self::assertNull($result->meta['gallery']['date']);
    }

    public function test_gallery_rejects_a_page_without_a_title(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Gallery($this->clientWithHtml('<html><body><main>nothing</main></body></html>')))->execute(new CrawlRequestDto(url: self::BASE . '/content/j6R'));
    }

    public function test_listing_extracts_every_card_field_without_navigation(): void
    {
        $result = (new Listing($this->clientWithFixture('gravurech/listing-new-photo.html')))->execute(new CrawlRequestDto(url: self::BASE . '/search/new/photo'));

        self::assertSame('gallery', $result->entityType);
        self::assertCount(60, $result->items);
        $first = $result->items[0];
        self::assertSame(self::BASE . '/content/j6R', $first->url);
        self::assertSame('gallery', $first->entityType);
        self::assertSame('gallery', $first->nextCrawlType);
        $gallery = $first->meta['gallery'];
        self::assertSame('j6R', $gallery['external_id']);
        self::assertSame('澄田綾乃 002', $gallery['title']);
        self::assertSame(['フォト'], $gallery['categories']);
        self::assertSame('1713', $gallery['metadata']['content_id']);
        self::assertSame(self::BASE . '/storage/newPhoto/1713_thumbnail1.jpg', $gallery['metadata']['thumbnail_url']);
        self::assertSame('pYn', $result->items[1]->meta['gallery']['external_id']);
        self::assertSame('尾木波菜 001', $result->items[1]->meta['gallery']['title']);
        self::assertSame(60, count(array_unique(array_map(static fn($item): string => $item->url, $result->items))));
    }

    public function test_listing_paginates_from_the_first_page(): void
    {
        $result = (new Listing($this->clientWithFixture('gravurech/listing-new-photo.html')))->execute(new CrawlRequestDto(url: self::BASE . '/search/new/photo'));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(6, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/search/new/photo?page=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_listing_last_page_has_no_next_page(): void
    {
        $result = (new Listing($this->clientWithFixture('gravurech/listing-new-photo-page-6.html')))->execute(new CrawlRequestDto(url: self::BASE . '/search/new/photo?page=6'));

        self::assertCount(5, $result->items);
        self::assertSame('3QB', $result->items[0]->meta['gallery']['external_id']);
        self::assertSame('たると 001', $result->items[0]->meta['gallery']['title']);
        self::assertSame('48', $result->items[0]->meta['gallery']['metadata']['content_id']);
        self::assertSame('XD', $result->items[4]->meta['gallery']['external_id']);
        self::assertSame(6, $result->pagination->currentPage);
        self::assertSame(6, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_listing_rejects_a_page_without_cards(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><main>nothing</main></body></html>')))->execute(new CrawlRequestDto(url: self::BASE . '/search/new/photo'));
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('gravurech');
        self::assertInstanceOf(GravurechCrawler::class, $crawler);

        $expected = [
            '/search/new/photo' => CrawlType::Listing,
            '/search/new/photo?page=3' => CrawlType::Listing,
            '/content/j6R' => CrawlType::Gallery,
            '/content/2n' => CrawlType::Gallery,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/search/cast/170')->matched);
        self::assertSame('gravurech', $crawler->name());
    }
}
