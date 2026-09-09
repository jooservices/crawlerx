<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\FfJav\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class FfJavDetailTest extends TestCase
{
    public function test_detail_parses_item_from_fixture(): void
    {
        $url = 'https://ffjav.com/torrent/mida-768';
        $client = $this->clientWithFixture('ffjav/detail-sample-1.html');

        $result = (new Detail($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame($url, $result->url);
        self::assertNotSame('', (string) $result->meta['movie']['external_id']);
        self::assertNotSame('', (string) $result->meta['movie']['title']);
        self::assertNotSame('', (string) ($result->meta['movie']['code'] ?? ''));
    }

    public function test_detail_throws_on_empty_html(): void
    {
        $client = $this->clientWithHtml('');

        $this->expectException(RuntimeException::class);

        (new Detail($client))->execute(new CrawlRequestDto(url: 'https://ffjav.com/torrent/test', type: CrawlType::Detail));
    }
}
