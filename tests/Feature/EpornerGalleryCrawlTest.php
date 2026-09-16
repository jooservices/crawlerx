<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class EpornerGalleryCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_gallery_crawl_tolerates_missing_optional_metadata(): void
    {
        $url = 'https://www.eporner.com/gallery/xKeoFe7VHmO/Minimal/';
        $html = <<<'HTML'
        <html><body>
        <div class="gallery-heading"><h1>Minimal</h1></div>
        <div class="gallery-meta"><span><strong>2</strong> Photos</span></div>
        <div class="photosgrid gallerygrid">
        <div class="mbphoto2" data-gallery-photo="111"><div class="gallery-photo-inner">
        <a class="gallery-photo-image" href="/photo/AbCd/photo-one/"><img src="https://static-ca-cdn.eporner.com/gallery/a/b/111-photo-one_296x1000.jpg"></a>
        </div></div>
        <div class="mbphoto2" data-gallery-photo="222"><div class="gallery-photo-inner">
        <a class="gallery-photo-image" href="/photo/ZzYy/photo-two/"></a>
        </div></div>
        </div>
        </body></html>
        HTML;

        FixtureResponder::for('GET', $url)->body($html);
        $result = CrawlerX::url($url)->crawl();

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertSame('Minimal', $gallery['title']);
        self::assertSame(2, $gallery['photo_count']);
        self::assertNull($gallery['views']);
        self::assertNull($gallery['votes']);
        self::assertNull($gallery['rating']);
        self::assertCount(2, $gallery['photos']);
        self::assertNull($gallery['photos'][0]['position']);
        self::assertNull($gallery['photos'][1]['image_url']);
    }

    public function test_gallery_without_photos_throws_parse_exception(): void
    {
        $url = 'https://www.eporner.com/gallery/xKeoFe7VHmO/Empty/';
        FixtureResponder::for('GET', $url)->body('<html><body><div class="gallery-heading"><h1>Empty</h1></div></body></html>');

        $this->expectException(CrawlParseException::class);

        CrawlerX::url($url)->crawl();
    }

    public function test_gallery_with_explicit_site_and_type_uses_non_gallery_url(): void
    {
        $url = 'https://www.eporner.com/whatever/not-a-gallery/';
        FixtureResponder::for('GET', $url)->body(
            '<html><body><div class="gallery-heading"><h1>Odd</h1></div>'
            . '<div class="photosgrid gallerygrid"><div class="mbphoto2" data-gallery-photo="111">'
            . '<div class="gallery-photo-inner"><a class="gallery-photo-image" href="/photo/AbCd/one/">'
            . '<img src="https://static-ca-cdn.eporner.com/gallery/a/b/111-one_296x1000.jpg"></a></div></div></div>'
            . '</body></html>',
        );

        $result = CrawlerX::site('eporner')->url($url)->type(CrawlType::Gallery)->crawl();

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertNull($gallery['external_id']);
        self::assertSame('Odd', $gallery['title']);
        self::assertCount(1, $gallery['photos']);
    }

    public function test_gallery_dto_and_photo_dto_from_parsed_handle_array_input(): void
    {
        $item = GalleryDto::fromParsed(
            externalId: 'gallery-1',
            title: 'Gallery One',
            data: [
                'performers' => ['Actress A', ''],
                'photos' => [
                    new PhotoDto(id: 'keep-instance'),
                    [
                        'id' => '111',
                        'image_url' => 'https://cdn.eporner.com/111-one.jpg',
                        'thumbnail_url' => 'https://cdn.eporner.com/111-one_296x1000.jpg',
                        'position' => 1,
                        'views' => 5,
                        'rating' => '100%',
                    ],
                ],
                'custom_field' => 'kept-in-metadata',
            ],
        )->toItem('https://www.eporner.com/gallery/gallery-1/Gallery-One/');

        $serialized = $item->toArray();

        self::assertSame('gallery', $serialized['entity_type']);
        self::assertSame('kept-in-metadata', $serialized['meta']['gallery']['metadata']['custom_field']);
        self::assertSame(['Actress A'], $serialized['meta']['gallery']['performers']);
        self::assertCount(2, $serialized['meta']['gallery']['photos']);
        self::assertSame('keep-instance', $serialized['meta']['gallery']['photos'][0]['id']);
        self::assertSame('https://cdn.eporner.com/111-one.jpg', $serialized['meta']['gallery']['photos'][1]['image_url']);

        self::assertSame('Gallery', ImportEntity::Gallery->label());
    }
}
