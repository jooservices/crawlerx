<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class EntityDtoContractTest extends TestCase
{
    public function test_movie_item_has_only_public_root_fields_and_one_meta_entity(): void
    {
        $item = (new MovieDto(
            externalId: 'NAMH-074',
            title: 'Some title',
            code: 'NAMH-074',
            performers: [new PerformerDto(externalId: 'abc1', name: 'Actress A')],
            tags: ['Drama'],
        ))->toItem('https://www.javbus.com/en/NAMH-074');

        $serialized = $item->toArray();

        self::assertSame(['url', 'entity_type', 'meta'], array_keys($serialized));
        self::assertSame('movie', $serialized['entity_type']);
        self::assertSame(['movie'], array_keys($serialized['meta']));
        self::assertSame('NAMH-074', $serialized['meta']['movie']['external_id']);
        self::assertSame('Actress A', $serialized['meta']['movie']['performers'][0]['name']);
        self::assertArrayNotHasKey('external_id', $serialized);
        self::assertArrayNotHasKey('title', $serialized);
        self::assertArrayNotHasKey('track_id', $serialized);
    }

    public function test_performer_list_uses_items_and_performer_entity_type(): void
    {
        $item = (new PerformerDto(externalId: 'abc1', name: 'Actress A'))
            ->toItem('https://example.test/star/abc1');
        $list = new CrawlListResultDto(
            url: 'https://example.test/stars',
            page: 1,
            entityType: 'performer',
            items: [$item],
            pagination: new CrawlPaginationDto(1, 1, null, null, false),
        );

        $serialized = $list->toArray();

        self::assertSame(['url', 'page', 'entity_type', 'items', 'pagination'], array_keys($serialized));
        self::assertSame('performer', $serialized['entity_type']);
        self::assertSame(['performer'], array_keys($serialized['items'][0]['meta']));
        self::assertSame('Actress A', $serialized['items'][0]['meta']['performer']['name']);
        self::assertArrayNotHasKey('performers', $serialized);
    }

    public function test_gallery_item_serializes_photo_list_and_metadata(): void
    {
        $item = GalleryDto::fromParsed(
            externalId: 'gallery-1',
            title: 'Gallery One',
            data: [
                'photo_count' => 2,
                'views' => 100,
                'rating' => '95%',
                'votes' => 7,
                'uploader' => 'uploader-x',
                'uploader_url' => 'https://www.eporner.com/profile/uploader-x/',
                'date' => '2025-01-01',
                'performers' => ['Actress A', ''],
                'categories' => ['Japanese'],
                'tags' => ['Uncensored'],
                'photos' => [
                    [
                        'id' => '111',
                        'url' => 'https://www.eporner.com/photo/AbCd/one/',
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
        self::assertSame(['gallery'], array_keys($serialized['meta']));
        self::assertSame('gallery-1', $serialized['meta']['gallery']['external_id']);
        self::assertSame(2, $serialized['meta']['gallery']['photo_count']);
        self::assertSame(['Actress A'], $serialized['meta']['gallery']['performers']);
        self::assertSame('kept-in-metadata', $serialized['meta']['gallery']['metadata']['custom_field']);

        $photo = $serialized['meta']['gallery']['photos'][0] ?? null;
        self::assertIsArray($photo);
        self::assertSame('111', $photo['id']);
        self::assertSame('https://cdn.eporner.com/111-one.jpg', $photo['image_url']);
        self::assertSame('https://cdn.eporner.com/111-one_296x1000.jpg', $photo['thumbnail_url']);
        self::assertSame(1, $photo['position']);
        self::assertSame(5, $photo['views']);
        self::assertSame('100%', $photo['rating']);
    }

    public function test_photo_from_parsed_keeps_unknown_keys_in_metadata(): void
    {
        $photo = PhotoDto::fromParsed([
            'id' => '222',
            'url' => 'https://www.eporner.com/photo/ZzYy/two/',
            'image_url' => 'https://cdn.eporner.com/222-two.jpg',
            'thumbnail_url' => 'https://cdn.eporner.com/222-two_296x1000.jpg',
            'position' => 2,
            'views' => 9,
            'rating' => '80%',
            'orientation' => 'portrait',
        ]);

        $serialized = $photo->toArray();

        self::assertSame('222', $serialized['id']);
        self::assertSame('https://cdn.eporner.com/222-two.jpg', $serialized['image_url']);
        self::assertSame(2, $serialized['position']);
        self::assertSame('portrait', $serialized['metadata']['orientation']);
    }
}
