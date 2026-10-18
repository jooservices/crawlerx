<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvfanProfiles;

use JOOservices\CrawlerX\Adapters\AvfanProfiles\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_profile_yields_height_clean_birth_ranking_counts_and_works(): void
    {
        $p = (new PerformerDetail($this->clientWithFixture('AvfanProfiles/detail-arisu-mai.html')))
            ->execute(new CrawlRequestDto(url: 'https://av-fan.tokyo/actress/%E6%9C%89%E6%A0%96%E8%88%9E%E8%A1%A3.html', type: CrawlType::PerformerDetail))->meta['performer'];

        self::assertSame('2003/03/06', $p['birth_date_raw']);
        self::assertSame('161cm', $p['height_raw']);

        $meta = $p['metadata'];
        self::assertSame(['category' => 'シロウトTV', 'period' => '年間', 'rank' => 687], $meta['ranking']);
        self::assertSame(331, $meta['store_counts']['FANZA']);
        self::assertSame(15, $meta['store_counts']['VR動画']);
        self::assertNotEmpty($meta['works']);
        self::assertSame('やっぱりオンナは黒髪に限る 清楚系美女二名に生中出し敢行002', $meta['works'][0]['title']);
        self::assertSame(['有栖舞衣', '菊池はる'], $meta['works'][0]['performers']);
        self::assertSame('マザー', $meta['works'][0]['maker']);
        self::assertSame('2026-08-31', $meta['works'][0]['date']);
    }
}
