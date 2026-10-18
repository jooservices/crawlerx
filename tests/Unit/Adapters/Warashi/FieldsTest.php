<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Warashi;

use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_profile_yields_photo_galleries_and_filmography(): void
    {
        $p = (new PerformerDetail($this->clientWithFixture('warashi/detail_yua_mikami.html')))
            ->execute(new CrawlRequestDto(url: 'https://warashi-asian-pornstars.fr/en/s-2-0/yua-mikami/asian-female-pornstar/1234', type: CrawlType::PerformerDetail))->meta['performer'];

        $meta = $p['metadata'];
        self::assertCount(2, $meta['photo_galleries']);
        self::assertSame(16, $meta['photo_galleries'][0]['pictures']);
        self::assertStringContainsString('/photo-gallery/11059', $meta['photo_galleries'][0]['url']);

        self::assertCount(1, $meta['filmography']);
        self::assertSame('3687', $meta['filmography'][0]['external_id']);
        self::assertSame('Asian Dollhouse No Boys Allowed', $meta['filmography'][0]['title']);
        self::assertSame('Third World Media', $meta['filmography'][0]['studio']);
        self::assertSame('2011-00-00', $meta['filmography'][0]['date']);

        self::assertArrayNotHasKey('biography', $meta);
        self::assertArrayNotHasKey('links', $meta);
    }
}
