<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Warashi;

use JOOservices\CrawlerX\Adapters\Warashi\Types\Gallery;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class GalleryTest extends TestCase
{
    public function test_gallery_parses_metadata_and_all_photos_from_live_fixture(): void
    {
        $client = $this->clientWithFixture('warashi/gallery-yuna-ishikawa.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-7-0/yuna-ishikawa/female-asian-pornstar/photo-gallery/56092',
            type: CrawlType::Gallery,
        ));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('gallery', $result->entityType);

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertSame('56092', $gallery['external_id']);
        self::assertSame('Yûna ISHIKAWA - 石川祐奈 - photo gallery 016', $gallery['title']);
        self::assertSame(9, $gallery['photo_count']);
        self::assertContains('Yûna ISHIKAWA - 石川祐奈', $gallery['performers']);
        self::assertSame('caribbeancom', $gallery['metadata']['source']);
        self::assertSame('/en/s-2-0/yuna-ishikawa/asian-female-pornstar/3490', $gallery['metadata']['performer_url']);
    }

    public function test_gallery_parses_full_resolution_photo_urls_with_thumbnails(): void
    {
        $client = $this->clientWithFixture('warashi/gallery-yuna-ishikawa.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-7-0/yuna-ishikawa/female-asian-pornstar/photo-gallery/56092',
            type: CrawlType::Gallery,
        ));

        $photos = $result->meta['gallery']['photos'] ?? null;
        self::assertIsArray($photos);
        self::assertCount(9, $photos);

        $first = $photos[0] ?? null;
        self::assertIsArray($first);
        self::assertSame(1, $first['position']);
        self::assertSame(
            'https://warashi-asian-pornstars.fr/WAPdB-img/pornostars-f-galeries/56000/56092/large/wapdb-yuna-ishikawa-pornostar-asiatique.warashi-asian-pornstars.fr-56092-001.jpg',
            $first['image_url'],
        );
        self::assertSame(
            'https://warashi-asian-pornstars.fr/WAPdB-img/pornostars-f-galeries/56000/56092/mini/wapdb-yuna-ishikawa-pornostar-asiatique.warashi-asian-pornstars.fr-56092-001.jpg',
            $first['thumbnail_url'],
        );
        self::assertSame($first['image_url'], $first['url']);
    }

    public function test_gallery_photos_are_uniquely_positioned(): void
    {
        $client = $this->clientWithFixture('warashi/gallery-yuna-ishikawa.html');
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-7-0/yuna-ishikawa/female-asian-pornstar/photo-gallery/56092',
            type: CrawlType::Gallery,
        ));

        $photos = $result->meta['gallery']['photos'] ?? [];
        $positions = array_map(static fn(array $photo): int => $photo['position'], $photos);

        self::assertSame(range(1, 9), $positions);
    }

    public function test_gallery_throws_when_page_has_no_photos(): void
    {
        $client = $this->clientWithHtml('<html><body><h1>Empty gallery</h1></body></html>');

        $this->expectException(CrawlParseException::class);

        (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-7-0/empty/photo-gallery/99999',
            type: CrawlType::Gallery,
        ));
    }

    public function test_gallery_tolerates_missing_optional_metadata(): void
    {
        $html = <<<'HTML'
        <html><body>
        <div id="main">
        <h1>Minimal Gallery</h1>
        <div id="fiche-galerie-listing-photos">
        <h2>pictures</h2>
        <a href="/WAPdB-img/galeries/1/large/photo-001.jpg"><img src="/WAPdB-img/galeries/1/mini/photo-001.jpg"></a>
        <a href="/WAPdB-img/galeries/1/large/photo-002.jpg"><img src="/WAPdB-img/galeries/1/mini/photo-002.jpg"></a>
        </div>
        </div>
        </body></html>
        HTML;

        $client = $this->clientWithHtml($html);
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-7-0/minimal/female-asian-pornstar/photo-gallery/1',
            type: CrawlType::Gallery,
        ));

        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);
        self::assertSame('Minimal Gallery', $gallery['title']);
        self::assertSame('1', $gallery['external_id']);
        self::assertSame(2, $gallery['photo_count']);
        self::assertSame([], $gallery['performers']);
        self::assertNull($gallery['metadata']['source']);
        self::assertCount(2, $gallery['photos']);

        $first = $gallery['photos'][0] ?? null;
        self::assertIsArray($first);
        self::assertSame(1, $first['position']);
        self::assertSame('https://warashi-asian-pornstars.fr/WAPdB-img/galeries/1/large/photo-001.jpg', $first['image_url']);
    }

    public function test_gallery_falls_back_to_external_id_when_title_missing(): void
    {
        $html = '<html><body><div id="fiche-galerie-listing-photos">'
            . '<a href="/WAPdB-img/galeries/1/large/photo-001.jpg"><img src="/WAPdB-img/galeries/1/mini/photo-001.jpg"></a>'
            . '</div></body></html>';

        $client = $this->clientWithHtml($html);
        $result = (new Gallery($client))->execute(new CrawlRequestDto(
            url: 'https://warashi-asian-pornstars.fr/en/s-7-0/minimal/female-asian-pornstar/photo-gallery/42',
            type: CrawlType::Gallery,
        ));

        self::assertSame('42', $result->meta['gallery']['external_id']);
        self::assertSame('42', $result->meta['gallery']['title']);
    }
}
