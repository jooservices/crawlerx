<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavPhotos;

use JOOservices\CrawlerX\Adapters\JavPhotos\Types\Gallery;
use JOOservices\CrawlerX\Adapters\JavPhotos\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class GalleryTest extends TestCase
{
    public function test_gallery_parses_metadata_and_all_photos_from_live_fixture(): void
    {
        $client = $this->clientWithFixture('javphotos/gallery-hana-aoyama.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/1pondo-hana-aoyama-sexist-mobile-pics',
            type: CrawlType::Gallery,
        ));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('gallery', $result->entityType);

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertSame('1pondo-hana-aoyama-sexist-mobile-pics', $gallery['external_id']);
        self::assertStringContainsString('Hana Aoyama', (string) $gallery['title']);
        self::assertSame(24, $gallery['photo_count']);
        self::assertContains('Hana Aoyama', $gallery['performers']);
        self::assertSame('111424_001', $gallery['metadata']['movie_code']);
    }

    public function test_gallery_parses_full_resolution_photo_urls_with_thumbnails(): void
    {
        $client = $this->clientWithFixture('javphotos/gallery-hana-aoyama.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/1pondo-hana-aoyama-sexist-mobile-pics',
            type: CrawlType::Gallery,
        ));

        $photos = $result->meta['gallery']['photos'] ?? null;
        self::assertIsArray($photos);
        self::assertCount(24, $photos);

        $first = $photos[0] ?? null;
        self::assertIsArray($first);
        self::assertSame(1, $first['position']);
        self::assertSame(
            'https://jav.photos/pictures/1pondo/hana-aoyama/111424_001/hana-aoyama-1.jpg',
            $first['image_url'],
        );
        self::assertSame(
            'https://jav.photos/pics/1pondo/hana-aoyama/111424_001/hd-hana-aoyama-1.jpg',
            $first['thumbnail_url'],
        );
        self::assertSame($first['image_url'], $first['url']);
    }

    public function test_gallery_photos_are_uniquely_positioned(): void
    {
        $client = $this->clientWithFixture('javphotos/gallery-hana-aoyama.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/1pondo-hana-aoyama-sexist-mobile-pics',
            type: CrawlType::Gallery,
        ));

        $photos = $result->meta['gallery']['photos'] ?? [];
        $positions = array_map(static fn(array $photo): int => $photo['position'], $photos);

        self::assertSame(range(1, 24), $positions);
    }

    public function test_gallery_throws_when_page_has_no_photos(): void
    {
        $client = $this->clientWithHtml('<html><body><title>Empty gallery</title></body></html>');

        $this->expectException(CrawlParseException::class);

        (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/empty-gallery',
            type: CrawlType::Gallery,
        ));
    }

    public function test_gallery_tolerates_missing_optional_metadata(): void
    {
        $html = <<<'HTML'
        <html><body>
        <title>Jav Photos Free Minimal Gallery HD Porn Pics Gallery</title>
        <div class="content">
        <div class="pinbox pinimg"><a href="/pictures/site/model/123456/model-1.jpg"><img src="/pics/site/model/123456/hd-model-1.jpg"></a></div>
        <div class="pinbox pinimg"><a href="/pictures/site/model/123456/model-2.jpg"><img src="/pics/site/model/123456/hd-model-2.jpg"></a></div>
        </div>
        </body></html>
        HTML;

        $client = $this->clientWithHtml($html);
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/minimal-gallery',
            type: CrawlType::Gallery,
        ));

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertSame('Minimal Gallery', $gallery['title']);
        self::assertSame('minimal-gallery', $gallery['external_id']);
        self::assertSame(2, $gallery['photo_count']);
        self::assertSame([], $gallery['performers']);
        self::assertSame('123456', $gallery['metadata']['movie_code']);
        self::assertCount(2, $gallery['photos']);

        $first = $gallery['photos'][0] ?? null;
        self::assertIsArray($first);
        self::assertSame(1, $first['position']);
        self::assertSame('https://jav.photos/pictures/site/model/123456/model-1.jpg', $first['image_url']);
    }

    public function test_gallery_falls_back_to_external_id_when_title_missing(): void
    {
        $html = '<html><body><div class="content">'
            . '<div class="pinbox pinimg"><a href="/pictures/site/model/123456/model-1.jpg"><img src="/pics/site/model/123456/hd-model-1.jpg"></a></div>'
            . '</div></body></html>';

        $client = $this->clientWithHtml($html);
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/no-title-gallery',
            type: CrawlType::Gallery,
        ));

        self::assertSame('no-title-gallery', $result->meta['gallery']['external_id']);
        self::assertSame('no-title-gallery', $result->meta['gallery']['title']);
    }

    public function test_listing_parses_gallery_cards_with_next_crawl_type(): void
    {
        $client = $this->clientWithFixture('javphotos/listing-page1.html');
        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/',
            type: CrawlType::Listing,
        ));

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertSame('gallery', $result->entityType);
        self::assertNotEmpty($result->items);

        $first = $result->items[0];
        self::assertStringContainsString('/free/', $first->url);
        self::assertSame('gallery', $first->nextCrawlType);
        self::assertNotSame('', trim((string) $first->meta['gallery']['title'] ?? ''));
        self::assertStringStartsWith('https://jav.photos/thumbs/', (string) $first->meta['gallery']['metadata']['thumbnail_url']);
    }

    public function test_listing_reports_next_page(): void
    {
        $client = $this->clientWithFixture('javphotos/listing-page1.html');
        $result = (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/',
            type: CrawlType::Listing,
        ));

        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame('https://jav.photos/free/2', $result->pagination->nextUrl);
    }

    public function test_listing_throws_when_no_gallery_cards(): void
    {
        $client = $this->clientWithHtml('<html><body><div class="content"></div></body></html>');

        $this->expectException(CrawlParseException::class);

        (new Listing($client))->execute(new CrawlRequestDto(
            url: 'https://jav.photos/free/',
            type: CrawlType::Listing,
        ));
    }
}
