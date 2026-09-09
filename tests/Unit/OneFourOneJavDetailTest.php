<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\OneFourOneJav\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class OneFourOneJavDetailTest extends TestCase
{
    public function test_detail_parses_item_from_fixture(): void
    {
        $url = 'https://www.141jav.com/torrent/SMOK039';
        $client = $this->clientWithFixture('141jav/detail-sample-1.html');

        $result = (new Detail($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame($url, $result->url);
        self::assertSame('SMOK039', $result->meta['movie']['external_id']);
        self::assertSame('SMOK039', $result->meta['movie']['title']);
        self::assertSame('SMOK-039', $result->meta['movie']['code']);
        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/smok039/smok039pl.jpg', $result->meta['movie']['cover_url']);
        self::assertSame(
            '[French Kiss Sexual Harassment] Alone In The Office During Overtime, Married Busty Female Boss Aoi Miumi Thrusts Her Long, Wet Tongue Into The Young Man\'s Mouth And Gives Him A Deep, Passionate French Kiss, Squeezing Out His Sperm.',
            $result->meta['movie']['description'],
        );
        self::assertSame('2026-05-30', $result->meta['movie']['date']);
        self::assertSame(['Kiss', 'Big Tits', 'Solowork', 'Creampie', 'OL'], $result->meta['movie']['tags']);
        self::assertSame('Aona Miu', $result->meta['movie']['performers'][0]['name']);
        self::assertSame('https://www.141jav.com/download/SMOK039.torrent', $result->meta['movie']['metadata']['download_url']);
    }

    public function test_detail_throws_on_empty_html(): void
    {
        $client = $this->clientWithHtml('');

        $this->expectException(RuntimeException::class);

        (new Detail($client))->execute(new CrawlRequestDto(url: 'https://www.141jav.com/torrent/test', type: CrawlType::Detail));
    }
}
