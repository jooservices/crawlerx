<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Aisex;

use JOOservices\CrawlerX\Adapters\Aisex\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_profile_yields_hobby_birthplace_tags_filmography_and_summary(): void
    {
        $p = $this->performer();
        $meta = $p['metadata'];

        self::assertSame('相内しおり', $p['name']);
        self::assertSame('1990-11-06', $p['birth_date_raw']);
        self::assertSame(['単体作品', '3P・4P', '潮吹き', 'クラシック', '中出し'], $p['tags']);
        self::assertSame('手芸、裁縫、料理', $meta['hobby']);
        self::assertSame('新潟県', $meta['birthplace']);
        self::assertStringContainsString('出身地新潟県', $meta['description']);
        self::assertStringStartsWith('相内しおりの出演作品を', $meta['trend_summary']);

        self::assertSame(['score' => 3.33, 'scale' => 5.0, 'votes' => null], $meta['review_average']);
        self::assertSame('300円 〜 400円', $meta['price_range']);
        self::assertSame('2010-07-10 〜 2026-05-25', $meta['release_range']);
        self::assertSame(2, $meta['label_count']);
        self::assertSame([['name' => 'SODクリエイト', 'count' => 5], ['name' => 'ワンズファクトリー', 'count' => 1]], $meta['top_makers']);
        self::assertSame('1sdca00017ai', $meta['sample_video']['content_id']);
    }

    public function test_the_filmography_has_dates_prices_and_shop_ids_without_the_affiliate_id(): void
    {
        $meta = $this->performer()['metadata'];

        self::assertCount(6, $meta['filmography']);
        self::assertSame('2025-09-22', $meta['filmography'][0]['date']);
        self::assertSame(300.0, $meta['filmography'][0]['price']['amount']);
        self::assertSame('1sdca00017ai', $meta['filmography'][0]['content_id']);
        self::assertSame('https://video.dmm.co.jp/av/content/?id=1sdca00017ai', $meta['filmography'][0]['url']);
        self::assertStringNotContainsString('petmovie', (string) json_encode($meta));

        self::assertSame([1, 2, 3], array_column($meta['popular_works'], 'rank'));
        self::assertStringNotContainsString('...', $meta['popular_works'][0]['title']);
        self::assertSame('https://video.dmm.co.jp/av/list/?actress=1004850/', $meta['fanza_url']);
    }

    public function test_related_actresses_are_linked_with_ids(): void
    {
        $related = $this->performer()['metadata']['related_performers'];

        self::assertNotEmpty($related);
        self::assertMatchesRegularExpression('/^\d+$/', (string) $related[0]['external_id']);
        self::assertStringStartsWith('https://www.aisex.jp/actress/', $related[0]['url']);
    }

    /** @return array<string, mixed> */
    private function performer(): array
    {
        return (new PerformerDetail($this->clientWithFixture('aisex/detail-aiuti-siori.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.aisex.jp/actress/1004850/', type: CrawlType::PerformerDetail))
            ->meta['performer'];
    }
}
