<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Jable;

use JOOservices\CrawlerX\Adapters\Jable\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class DetailTest extends TestCase
{
    public function test_detail_dsod_031_parses_keywords_performer_and_stripped_title(): void
    {
        $client = $this->clientWithFixture('jable/detail-dsod-031.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/dsod-031/',
            type: CrawlType::Detail,
        ));

        self::assertSame('DSOD-031', $result->meta['movie']['code']);
        self::assertStringNotContainsString('DSOD-031', (string) $result->meta['movie']['title']);
        self::assertSame('Sumire Kuramoto', $result->meta['movie']['performers'][0]['name']);
        self::assertSame(
            ['Uniform', 'Roleplay', 'Insult', 'Girl', 'Creampie', 'Short hair', 'School uniform', 'Insult', 'School', 'Spasms', 'Love potion', 'Avenge', 'Intrusion', '倉本すみれ'],
            $result->meta['movie']['tags'],
        );
        self::assertSame(108472, $result->meta['movie']['metadata']['views']);
    }

    public function test_detail_royd_348_parses_model_slug_performer_and_genres(): void
    {
        $client = $this->clientWithFixture('jable/detail-royd-348.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/royd-348/',
            type: CrawlType::Detail,
        ));

        self::assertSame('ROYD-348', $result->meta['movie']['code']);
        self::assertSame('百永さりな', $result->meta['movie']['performers'][0]['name']);
        self::assertCount(13, $result->meta['movie']['tags']);
        self::assertSame('百永さりな', $result->meta['movie']['tags'][12]);
    }

    public function test_detail_abf_382_parses_model_slug_performer(): void
    {
        $client = $this->clientWithFixture('jable/detail-abf-382.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/abf-382/',
            type: CrawlType::Detail,
        ));

        self::assertSame('ABF-382', $result->meta['movie']['code']);
        self::assertSame('Airi Suzumura', $result->meta['movie']['performers'][0]['name']);
        self::assertCount(10, $result->meta['movie']['tags']);
    }

    public function test_detail_sora_599_live_fixture_uses_cjk_fallback_when_model_href_is_hash(): void
    {
        $client = $this->clientWithFixture('jable/detail-sora-599.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/sora-599/',
            type: CrawlType::Detail,
        ));

        self::assertSame('SORA-599', $result->meta['movie']['code']);
        self::assertSame('柏木こなつ', $result->meta['movie']['performers'][0]['name']);
        self::assertSame('柏木こなつ', $result->meta['movie']['tags'][11]);
    }

    public function test_detail_jur_411_live_fixture_parses_roman_model_slug(): void
    {
        $client = $this->clientWithFixture('jable/detail-jur-411.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/jur-411/',
            type: CrawlType::Detail,
        ));

        self::assertSame('JUR-411', $result->meta['movie']['code']);
        self::assertSame('Non Ohana', $result->meta['movie']['performers'][0]['name']);
        self::assertSame('小花のん', $result->meta['movie']['tags'][9]);
    }

    public function test_detail_fjin_091_live_fixture_uses_cjk_fallback_when_model_href_is_hash(): void
    {
        $client = $this->clientWithFixture('jable/detail-fjin-091.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/fjin-091/',
            type: CrawlType::Detail,
        ));

        self::assertSame('FJIN-091', $result->meta['movie']['code']);
        self::assertSame('小那海あや', $result->meta['movie']['performers'][0]['name']);
        self::assertCount(10, $result->meta['movie']['tags']);
        self::assertIsArray($result->meta['movie']['metadata']['stream']);
        self::assertStringContainsString('.m3u8', (string) ($result->meta['movie']['metadata']['stream']['manifest_url'] ?? ''));
    }

    public function test_detail_pred_767_live_fixture_uses_cjk_fallback_when_model_href_is_hash(): void
    {
        $client = $this->clientWithFixture('jable/detail-pred-767.html');
        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/pred-767/',
            type: CrawlType::Detail,
        ));

        self::assertSame('PRED-767', $result->meta['movie']['code']);
        self::assertSame('和香なつき', $result->meta['movie']['performers'][0]['name']);
        self::assertSame('和香なつき', $result->meta['movie']['tags'][9]);
    }

    public function test_detail_fails_when_title_missing(): void
    {
        $client = $this->clientWithHtml('<html><body></body></html>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jable detail page did not contain expected movie fields.');

        (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.jable.tv/videos/empty/',
            type: CrawlType::Detail,
        ));
    }
}
