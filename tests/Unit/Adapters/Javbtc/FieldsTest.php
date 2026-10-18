<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Javbtc;

use JOOservices\CrawlerX\Adapters\Javbtc\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_clip_page_yields_actress_link_maker_link_duration_and_preview(): void
    {
        $m = (new Detail($this->clientWithFixture('javbtc/detail-sample-1.html')))
            ->execute(new CrawlRequestDto(url: 'https://javbtc.com/r18/s1no1style-yua-mikami-sougouwiki-celebrity', type: CrawlType::Detail))->meta['movie'];

        self::assertSame(['Yua Mikami'], array_column($m['performers'], 'name'));
        self::assertSame('yua-mikami', $m['performers'][0]['external_id']);
        self::assertSame('https://javbtc.com/r18/yua-mikami', $m['performers'][0]['url']);
        self::assertSame(12, $m['duration']);
        self::assertSame('S1no1style', $m['metadata']['maker']);
        self::assertSame('https://javbtc.com/r18/s1no1style', $m['metadata']['maker_url']);
        self::assertNotEmpty($m['metadata']['sample_video_url']);
    }
}
