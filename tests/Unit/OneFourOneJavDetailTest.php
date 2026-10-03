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
        $url = 'https://www.141jav.com/torrent/JUQ521';
        $client = $this->clientWithFixture('141jav/detail-sample-1.html');

        $result = (new Detail($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame($url, $result->url);
        self::assertSame('JUQ521', $result->meta['movie']['external_id']);
        self::assertSame('JUQ521', $result->meta['movie']['title']);
        self::assertSame('JUQ-521', $result->meta['movie']['code']);
        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/juq521/juq521pl.jpg', $result->meta['movie']['cover_url']);
        self::assertSame(
            "Celebrating Madonna's 20th Anniversary For 5 Consecutive Months! ! The Second Miraculous Collaboration! ! After Having Sex With My Husband To Make A Baby, My Father-in-law Keeps Creampieing Me... Nozomi Ishihara",
            $result->meta['movie']['description'],
        );
        self::assertSame('2024-01-19', $result->meta['movie']['date']);
        self::assertSame(['Conceived', 'Mature Woman', 'Married Woman', 'Solowork', 'Creampie'], $result->meta['movie']['tags']);
        self::assertSame('Ishihara Nozomi', $result->meta['movie']['performers'][0]['name']);
        self::assertSame('https://www.141jav.com/download/JUQ521.torrent', $result->meta['movie']['metadata']['download_url']);
    }

    public function test_detail_throws_on_empty_html(): void
    {
        $client = $this->clientWithHtml('');

        $this->expectException(RuntimeException::class);

        (new Detail($client))->execute(new CrawlRequestDto(url: 'https://www.141jav.com/torrent/test', type: CrawlType::Detail));
    }
}
