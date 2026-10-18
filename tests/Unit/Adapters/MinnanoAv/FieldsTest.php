<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\MinnanoAv;

use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_movie_page_yields_maker_label_links_and_shop_id(): void
    {
        $m = (new Detail($this->clientWithFixture('minnanoav/movie-detail.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.minnano-av.com/av159081.html', type: CrawlType::Detail))->meta['movie'];

        self::assertSame('OFJE-704', $m['code']);
        self::assertSame('2026-06-25', $m['date']);
        self::assertSame('エスワン ナンバーワンスタイル', $m['metadata']['maker']);
        self::assertSame('S1 NO.1 STYLE', $m['metadata']['label']);
        self::assertStringContainsString('/av_list.php?maker=', $m['metadata']['maker_url']);
        self::assertStringContainsString('/av_list.php?label=', $m['metadata']['label_url']);
        self::assertSame('ofje00704', $m['metadata']['fanza_content_id']);
        self::assertStringNotContainsString('minav1', (string) $m['metadata']['fanza_url']);
        self::assertGreaterThan(1, count($m['performers']));
    }
}
