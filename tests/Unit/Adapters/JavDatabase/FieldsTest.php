<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavDatabase;

use JOOservices\CrawlerX\Adapters\JavDatabase\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_profile_yields_rating_social_links_count_and_filmography(): void
    {
        $p = (new PerformerDetail($this->clientWithFixture('javdatabase/detail_yua_mikami.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.javdatabase.com/idols/yua-mikami/', type: CrawlType::PerformerDetail))->meta['performer'];

        self::assertSame('Yua Mikami', $p['name']);
        self::assertSame('三上悠亜', $p['name_japanese']);
        self::assertSame('1993-08-16', $p['birth_date_raw']);

        $meta = $p['metadata'];
        self::assertSame(['score' => 4.46, 'scale' => 5.0, 'votes' => 35], $meta['rating']);
        self::assertSame(363, $meta['movie_count']);
        self::assertContains('https://twitter.com/yua_mikami', array_column($meta['social_links'], 'url'));
        self::assertNotEmpty($meta['filmography']);
        self::assertArrayHasKey('code', $meta['filmography'][0]);
        self::assertStringContainsString('/movies/', $meta['filmography'][0]['url']);
        self::assertStringContainsString('ipage=2', $meta['filmography_next_url']);
    }
}
