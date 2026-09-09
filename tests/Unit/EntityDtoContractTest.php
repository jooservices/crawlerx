<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
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
}
