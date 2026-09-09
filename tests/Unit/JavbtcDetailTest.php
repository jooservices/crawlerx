<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Javbtc\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class JavbtcDetailTest extends TestCase
{
    public function test_detail_parses_item_from_fixture(): void
    {
        $url = 'https://javbtc.com/r18/s1no1style-yua-mikami-sougouwiki-celebrity';
        $client = $this->clientWithFixture('javbtc/detail-yua-mikami.html');

        $result = (new Detail($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame($url, $result->url);
        self::assertSame('s1no1style-yua-mikami-sougouwiki-celebrity', $result->meta['movie']['external_id']);
        self::assertSame('SSNI-00916', $result->meta['movie']['code']);
        self::assertSame('Yua Mikami', $result->meta['movie']['performers'][0]['name']);
        self::assertSame('S1no1style', $result->meta['movie']['metadata']['maker']);
        self::assertContains('Big Tits', $result->meta['movie']['tags']);
        self::assertContains('Hi Def', $result->meta['movie']['tags']);
        self::assertNotContains('Sougouwiki Celebrity', $result->meta['movie']['tags']);
        self::assertStringContainsString('Big Tits Bursting Out Of Her School Swimsuit', (string) $result->meta['movie']['title']);
        self::assertSame(
            'https://g.uuu.cam/movie/avgle/yua.mikami/video8321/1.jpg',
            $result->meta['movie']['cover_url'],
        );
        self::assertSame(
            'https://u.uuu.cam/movie/avgle/yua.mikami/video8321/1.mp4',
            $result->meta['movie']['metadata']['download_url'],
        );
        self::assertSame('direct', $result->meta['movie']['metadata']['downloads'][0]['type']);
        self::assertSame('avgle', $result->meta['movie']['metadata']['cdn_mirror']);
        self::assertSame('12:03', $result->meta['movie']['metadata']['clip_duration_label']);
    }

    public function test_detail_throws_on_empty_html(): void
    {
        $client = $this->clientWithHtml('');

        $this->expectException(RuntimeException::class);

        (new Detail($client))->execute(new CrawlRequestDto(url: 'https://javbtc.com/r18/test', type: CrawlType::Detail));
    }

    public function test_detail_throws_on_cloudflare_challenge(): void
    {
        $client = $this->clientWithHtml(
            '<html>Just a moment... challenges.cloudflare.com</html>',
            403,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Javbtc detail page is blocked or unavailable.');

        (new Detail($client))->execute(new CrawlRequestDto(url: 'https://javbtc.com/r18/test', type: CrawlType::Detail));
    }

    public function test_detail_throws_when_expected_movie_fields_are_missing(): void
    {
        $client = $this->clientWithHtml('<html><body><nav>JavBTC</nav></body></html>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Javbtc detail page did not contain expected movie fields.');

        (new Detail($client))->execute(new CrawlRequestDto(url: 'https://javbtc.com/r18/test', type: CrawlType::Detail));
    }
}
