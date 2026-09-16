<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Eporner;

use JOOservices\CrawlerX\Adapters\Eporner\Types\Gallery;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

final class GalleryTest extends TestCase
{
    public function test_gallery_parses_metadata_and_all_photos_from_live_fixture(): void
    {
        $client = $this->clientWithFixture('eporner/gallery-sample.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://www.eporner.com/gallery/xKeoFe7VHmO/Iori-Kogawa-gu-chuaniori-STAR-836-Uncensored-Leak/',
            type: CrawlType::Gallery,
        ));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('gallery', $result->entityType);

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertSame('xKeoFe7VHmO', $gallery['external_id']);
        self::assertSame('Iori Kogawa 古川いおり STAR-836 Uncensored Leak', $gallery['title']);
        self::assertSame(77, $gallery['photo_count']);
        self::assertIsInt($gallery['views']);
        self::assertGreaterThan(0, $gallery['views']);
        self::assertStringEndsWith('%', (string) $gallery['rating']);
        self::assertIsInt($gallery['votes']);
        self::assertSame('odour66', $gallery['uploader']);
        self::assertStringContainsString('/profile/odour66/', (string) $gallery['uploader_url']);
        self::assertSame('2025-05-23', $gallery['date']);
        self::assertContains('Iori Kogawa', $gallery['performers']);
        self::assertContains('Japanese', $gallery['categories']);
        self::assertNotEmpty($gallery['tags']);
    }

    public function test_gallery_parses_photo_list_with_full_resolution_urls(): void
    {
        $client = $this->clientWithFixture('eporner/gallery-sample.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://www.eporner.com/gallery/xKeoFe7VHmO/Iori-Kogawa-gu-chuaniori-STAR-836-Uncensored-Leak/',
            type: CrawlType::Gallery,
        ));

        $photos = $result->meta['gallery']['photos'] ?? null;
        self::assertIsArray($photos);
        self::assertCount(77, $photos);

        $first = $photos[0] ?? null;
        self::assertIsArray($first);
        self::assertSame('30766987', $first['id']);
        self::assertSame(1, $first['position']);
        self::assertSame('https://www.eporner.com/photo/A0zrB5ujeBd/MG-4472/', $first['url']);
        self::assertStringStartsWith('https://static-ca-cdn.eporner.com/gallery/', $first['thumbnail_url']);
        self::assertStringEndsWith('_296x1000.jpg', $first['thumbnail_url']);
        self::assertStringEndsWith('30766987-mg-4472.jpg', $first['image_url']);
        self::assertStringNotContainsString('_296x1000', $first['image_url']);
        self::assertIsInt($first['views']);
        self::assertSame('100%', $first['rating']);
    }

    public function test_gallery_photos_are_uniquely_positioned(): void
    {
        $client = $this->clientWithFixture('eporner/gallery-sample.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://www.eporner.com/gallery/xKeoFe7VHmO/Iori-Kogawa-gu-chuaniori-STAR-836-Uncensored-Leak/',
            type: CrawlType::Gallery,
        ));

        $photos = $result->meta['gallery']['photos'] ?? [];
        $ids = array_map(static fn(array $photo): string => (string) $photo['id'], $photos);
        $positions = array_map(static fn(array $photo): int => $photo['position'], $photos);

        self::assertCount(77, array_unique($ids));
        self::assertSame(range(1, 77), $positions);
    }

    public function test_gallery_throws_when_page_has_no_photos(): void
    {
        $client = $this->clientWithHtml('<html><body><div class="gallery-heading"><h1>Empty</h1></div></body></html>');

        $this->expectException(\JOOservices\CrawlerX\Exceptions\CrawlParseException::class);

        (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://www.eporner.com/gallery/xKeoFe7VHmO/Empty/',
            type: CrawlType::Gallery,
        ));
    }

    public function test_gallery_tolerates_missing_optional_metadata(): void
    {
        $html = <<<'HTML'
        <html><body>
        <div class="gallery-heading"><h1>Minimal</h1></div>
        <div class="gallery-meta"><span><strong>2</strong> Photos</span></div>
        <div class="photosgrid gallerygrid">
        <div class="mbphoto2" data-gallery-photo="111"><div class="gallery-photo-inner">
        <a class="gallery-photo-image" href="/photo/AbCd/photo-one/"><img src="https://static-ca-cdn.eporner.com/gallery/a/b/111-photo-one_296x1000.jpg"></a>
        <span class="gallery-photo-number" title="1 / 2"></span><span class="gallery-photo-full-views">5</span><span class="gallery-photo-rating">90%</span>
        </div></div>
        <div class="mbphoto2" data-gallery-photo="222"><div class="gallery-photo-inner">
        <a class="gallery-photo-image" href="/photo/ZzYy/photo-two/"><img src="https://static-ca-cdn.eporner.com/gallery/x/y/222-photo-two_296x1000.jpg"></a>
        </div></div>
        </div>
        </body></html>
        HTML;

        $client = $this->clientWithHtml($html);
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://www.eporner.com/gallery/xKeoFe7VHmO/Minimal/',
            type: CrawlType::Gallery,
        ));

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertSame('Minimal', $gallery['title']);
        self::assertSame('xKeoFe7VHmO', $gallery['external_id']);
        self::assertSame(2, $gallery['photo_count']);
        self::assertNull($gallery['views']);
        self::assertCount(2, $gallery['photos']);

        $second = $gallery['photos'][1] ?? null;
        self::assertIsArray($second);
        self::assertNull($second['position']);
        self::assertNull($second['views']);
        self::assertNull($second['rating']);
        self::assertStringEndsWith('222-photo-two.jpg', $second['image_url']);
    }

    public function test_gallery_falls_back_to_external_id_when_title_missing(): void
    {
        $html = '<html><body><div class="photosgrid gallerygrid"><div class="mbphoto2" data-gallery-photo="111">'
            . '<div class="gallery-photo-inner"><a class="gallery-photo-image" href="/photo/AbCd/photo-one/">'
            . '<img src="https://static-ca-cdn.eporner.com/gallery/a/b/111-photo-one_296x1000.jpg"></a></div></div></div></body></html>';

        $client = $this->clientWithHtml($html);
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://www.eporner.com/gallery/xKeoFe7VHmO/No-Title/',
            type: CrawlType::Gallery,
        ));

        self::assertSame('xKeoFe7VHmO', $result->meta['gallery']['external_id']);
        self::assertSame('xKeoFe7VHmO', $result->meta['gallery']['title']);
    }
}
