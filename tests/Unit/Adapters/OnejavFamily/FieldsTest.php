<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\OnejavFamily;

use JOOservices\CrawlerX\Adapters\FfJav\Types\Listing as FfJavListing;
use JOOservices\CrawlerX\Adapters\Onejav\Types\Detail as OnejavDetail;
use JOOservices\CrawlerX\Adapters\Onejav\Types\Listing as OnejavListing;
use JOOservices\CrawlerX\Adapters\OneFourOneJav\Types\Detail as Detail141;
use JOOservices\CrawlerX\Adapters\OneFourOneJav\Types\Listing as Listing141;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_141jav_detail_returns_size_magnet_torrent_and_linked_actresses(): void
    {
        $m = $this->detail141();

        self::assertSame('JUQ-521', $m['code']);
        self::assertSame('2024-01-19', $m['date']);
        self::assertSame('5.6 GB', $m['metadata']['download_size_label']);
        self::assertStringStartsWith('magnet:?xt=urn:btih:d5d58aea', $m['metadata']['magnets'][0]['url']);
        self::assertSame('5.6 GB', $m['metadata']['magnets'][0]['size_label']);
        self::assertSame('https://www.141jav.com/download/JUQ521.torrent', $m['metadata']['download_url']);
        self::assertSame('https://www.141jav.com/actress/Ishihara Nozomi', $m['performers'][0]['url']);
    }

    public function test_onejav_detail_returns_size_flag_and_actress_link(): void
    {
        $m = (new OnejavDetail($this->clientWithFixture('onejav/detail-sample-1.html')))
            ->execute(new CrawlRequestDto(url: 'https://onejav.com/torrent/ymds282', type: CrawlType::Detail))->meta['movie'];

        self::assertSame('7.5 GB', $m['metadata']['download_size_label']);
        self::assertTrue($m['metadata']['old_torrent']);
        self::assertSame('May 31, 2026', $m['metadata']['release_date_raw']);
        self::assertSame('真野祈', $m['performers'][0]['name']);
        self::assertStringContainsString('/actress/', (string) $m['performers'][0]['url']);
    }

    public function test_list_cards_carry_the_same_fields_as_the_detail_page(): void
    {
        $first = (new Listing141($this->clientWithFixture('141jav/listing-page-1.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.141jav.com/', type: CrawlType::Listing))->items[0]->meta['movie'];
        self::assertSame('UMD-1017', $first['code']);
        self::assertSame('2026-09-09', $first['date']);
        self::assertSame(['Married Woman'], $first['tags']);
        self::assertSame(['Misaki Hikari', 'Onosaka Yuika', 'Oshikawa Yuuri'], array_column($first['performers'], 'name'));
        self::assertSame('4.0 GB', $first['metadata']['download_size_label']);
        self::assertNotEmpty($first['metadata']['magnets']);

        $onejav = (new OnejavListing($this->clientWithFixture('onejav/listing-page-1.html')))
            ->execute(new CrawlRequestDto(url: 'https://onejav.com/', type: CrawlType::Listing))->items[0]->meta['movie'];
        self::assertSame('6.2 GB', $onejav['metadata']['download_size_label']);
        self::assertSame('2026-09-09', $onejav['date']);

        $ffjav = (new FfJavListing($this->clientWithFixture('ffjav/listing-page-1.html')))
            ->execute(new CrawlRequestDto(url: 'https://ffjav.com/', type: CrawlType::Listing))->items[0]->meta['movie'];
        self::assertSame('2026-09-08', $ffjav['date']);
        self::assertContains('神木麗', $ffjav['tags']);
        self::assertSame('5.0 GB', $ffjav['metadata']['download_size_label']);
    }

    /** @return array<string, mixed> */
    private function detail141(): array
    {
        return (new Detail141($this->clientWithFixture('141jav/detail-sample-1.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.141jav.com/torrent/JUQ521', type: CrawlType::Detail))->meta['movie'];
    }
}
