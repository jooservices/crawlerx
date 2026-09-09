<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\Onejav\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class OnejavDetailTest extends TestCase
{
    public function test_detail_parses_item_from_fixture(): void
    {
        $url = 'https://onejav.com/torrent/ymds282';
        $client = $this->clientWithFixture('onejav/detail-sample-1.html');

        $request = new CrawlRequestDto(url: $url, type: CrawlType::Detail);
        $result = (new Detail($client))->execute($request);

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame($url, $result->url);
        self::assertSame('ymds282', $result->meta['movie']['external_id']);
        self::assertSame('YMDS-282', $result->meta['movie']['code']);
        self::assertNotSame('', (string) $result->meta['movie']['title']);
        self::assertIsString($result->meta['movie']['metadata']['download_url'] ?? null);
        self::assertSame('7.5 GB', $result->meta['movie']['metadata']['download_size_label']);
        self::assertSame(8053063680, $result->meta['movie']['metadata']['download_size_bytes']);
    }

    public function test_detail_throws_on_empty_html(): void
    {
        $client = $this->clientWithHtml('');

        $this->expectException(RuntimeException::class);

        $request = new CrawlRequestDto(url: 'https://onejav.com/torrent/test', type: CrawlType::Detail);
        (new Detail($client))->execute($request);
    }

    public function test_detail_throws_on_cloudflare_challenge(): void
    {
        $client = $this->clientWithHtml(
            '<html>Just a moment... challenges.cloudflare.com</html>',
            403,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Onejav detail page is blocked or unavailable.');

        $request = new CrawlRequestDto(url: 'https://onejav.com/torrent/test', type: CrawlType::Detail);
        (new Detail($client))->execute($request);
    }

    public function test_detail_throws_when_expected_movie_fields_are_missing(): void
    {
        $client = $this->clientWithHtml('<html><body><nav>OneJAV</nav></body></html>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Onejav detail page did not contain expected movie fields.');

        $request = new CrawlRequestDto(url: 'https://onejav.com/torrent/test', type: CrawlType::Detail);
        (new Detail($client))->execute($request);
    }
}
