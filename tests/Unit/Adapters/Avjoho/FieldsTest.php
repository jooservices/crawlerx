<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Avjoho;

use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_profile_yields_reading_tags_notable_works_and_similar_actresses(): void
    {
        $p = (new PerformerDetail($this->clientWithFixture('avjoho/detail-ichise-airi.html')))
            ->execute(new CrawlRequestDto(url: 'https://db.avjoho.com/%e5%b8%82%e7%80%ac%e3%81%82%e3%81%84%e3%82%8a/', type: CrawlType::PerformerDetail))->meta['performer'];

        self::assertSame('市瀬あいり', $p['metadata']['name_plain']);
        self::assertSame('2000年2月10日', $p['birth_date_raw']);
        self::assertContains('綺麗系', $p['tags']);
        self::assertContains('Gカップ', $p['tags']);

        $meta = $p['metadata'];
        self::assertSame('いちせあいり', $meta['name_kana']);
        self::assertSame('https://x.com/ichise_airi', $meta['sns_url']);
        self::assertCount(2, $meta['notable_works']);
        self::assertSame('pred00822', $meta['notable_works'][0]['content_id']);
        self::assertStringNotContainsString('dmmofu', json_encode($meta, JSON_UNESCAPED_UNICODE) ?: '');
        self::assertSame('https://video.dmm.co.jp/av/list/?actress=1106524', $meta['filmography_url']);
        self::assertCount(8, $meta['similar_performers']);
        self::assertSame('椎名ななみ', $meta['similar_performers'][0]['name']);
        self::assertSame('しいなななみ', $meta['similar_performers'][0]['name_kana']);
    }
}
