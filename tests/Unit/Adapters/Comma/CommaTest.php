<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Comma;

use JOOservices\CrawlerX\Adapters\Comma\CommaCrawler;
use JOOservices\CrawlerX\Adapters\Comma\Types\Detail;
use JOOservices\CrawlerX\Adapters\Comma\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Support\MovieMeta;
use JOOservices\CrawlerX\Tests\TestCase;

final class CommaTest extends TestCase
{
    private const BASE = 'https://comma.co.jp';

    private const DETAIL_URL = self::BASE . '/dvd/comt-070b/';

    public function test_listing_returns_every_release_card_with_code_title_cover_and_detail_url(): void
    {
        $result = (new Listing($this->clientWithFixture('comma/listing-2024.html')))->execute(new CrawlRequestDto(
            url: self::BASE . '/ym/2024/',
        ));

        self::assertSame('movie', $result->entityType);
        self::assertCount(4, $result->items);
        $first = $result->items[0];
        self::assertSame(self::BASE . '/dvd/comt-077b/', $first->url);
        self::assertSame('COMT-077B', $first->meta['movie']['external_id']);
        self::assertSame('COMT-077B', $first->meta['movie']['code']);
        self::assertSame('スペース・シャーク', $first->meta['movie']['title']);
        self::assertSame(self::BASE . '/wp-content/uploads/2024/08/COMT-077B_HP.jpg', $first->meta['movie']['cover_url']);
        self::assertSame('COMMA VISION', $first->meta['movie']['metadata']['maker']);
        self::assertSame(
            ['COMT-077B', 'COMT-076B', 'COMT-075B', 'COMT-074B'],
            array_map(static fn($item): string => $item->meta['movie']['code'], $result->items),
        );
    }

    public function test_listing_has_one_page_because_the_archive_has_no_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('comma/listing-2024.html')))->execute(new CrawlRequestDto(
            url: self::BASE . '/ym/2024/',
        ));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_listing_rejects_a_page_without_release_cards(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><main>nothing</main></body></html>')))->execute(new CrawlRequestDto(
            url: self::BASE . '/ym/2024/',
        ));
    }

    public function test_detail_maps_every_labelled_field_to_its_standard_key(): void
    {
        $item = (new Detail($this->clientWithFixture('comma/detail-comt-070b.html')))->execute(new CrawlRequestDto(
            url: self::DETAIL_URL,
        ));
        $movie = $item->meta['movie'];
        $metadata = $movie['metadata'];

        self::assertSame(self::DETAIL_URL, $item->url);
        self::assertSame('COMT-070B', $movie['external_id']);
        self::assertSame('COMT-070B', $movie['code']);
        self::assertSame('パペットシャーク', $movie['title']);
        self::assertStringStartsWith('セールス・ポイント／解説', (string) $movie['description']);
        self::assertStringNotContainsString('原題', (string) $movie['description']);
        self::assertSame('2023-07-06', $movie['date']);
        self::assertSame(68, $movie['duration']);
        self::assertSame(
            ['グレイソン・ケリー', 'チャンス・ケリー', 'ブレット・ケリー', 'ダレン・スティーヴンス', 'ヴェロニカ・ダルク', 'テレサ・パイク', 'アダム・ゴールドバーグ', 'トレバー・ぺイヤー', 'チャド・ウォールズ', 'キム・フレッチャー'],
            array_column($movie['performers'], 'name'),
        );
        self::assertSame(['B級映画', 'SF', 'サメ', 'サメ映画'], $movie['tags']);
        self::assertSame('COMMA VISION', $metadata['maker']);
        self::assertSame('Treasure Hollywood', $metadata[MovieMeta::SERIES]);
        self::assertStringStartsWith('グレイソン・ケリー／チャンス・ケリー', (string) $metadata[MovieMeta::DIRECTOR]);
        self::assertSame(['amount' => 4950.0, 'currency' => 'JPY', 'label' => '¥ 4,500- （税込 ¥4,950-）'], $metadata[MovieMeta::PRICE]);
        self::assertSame('2023年7月07月06日', $metadata[MovieMeta::RELEASE_DATE_RAW]);
        self::assertSame('PUPPET SHARK', $metadata[MovieMeta::ORIGINAL_TITLE]);
        self::assertSame(15, $metadata['special_video_duration']);
        self::assertSame('https://landshark.crayonsite.com/', $metadata['special_site_url']);
    }

    public function test_detail_keeps_every_labelled_row_as_source_fields(): void
    {
        $item = (new Detail($this->clientWithFixture('comma/detail-comt-070b.html')))->execute(new CrawlRequestDto(
            url: self::DETAIL_URL,
        ));
        $fields = $item->meta['movie']['metadata']['source_fields'];

        self::assertSame(
            ['出演者', '品番', 'シリーズ', '監督', '価格', '収録時間', '発売日', 'タグ', 'コメント', '特別映像'],
            array_keys($fields),
        );
        self::assertSame('COMT-070B', $fields['品番']);
        self::assertSame('68分', $fields['収録時間']);
        self::assertSame('15分', $fields['特別映像']);
    }

    public function test_detail_images_are_the_cover_and_every_work_image_in_the_page(): void
    {
        $item = (new Detail($this->clientWithFixture('comma/detail-comt-070b.html')))->execute(new CrawlRequestDto(
            url: self::DETAIL_URL,
        ));
        $movie = $item->meta['movie'];
        $cover = self::BASE . '/wp-content/uploads/2023/04/0a72fa645c9544b12ecc08ff23b5fe77.png';

        self::assertSame($cover, $movie['cover_url']);
        self::assertSame([$cover], array_column($movie['screenshots'], 'url'));
        self::assertNull($movie['screenshots'][0]['thumbnail_url']);
    }

    public function test_url_detection_matches_only_comma_listing_and_detail_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('comma');
        self::assertInstanceOf(CommaCrawler::class, $crawler);

        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/ym/2024/')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/ym/2026')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::DETAIL_URL)->crawlType);
        self::assertFalse($crawler->detectUrl(self::BASE . '/dvd/')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/ym/202307/x/')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/company/')->matched);
        self::assertFalse($crawler->detectUrl('https://comma.jp/dvd/comt-070b/')->matched);
        self::assertSame('comma', $crawler->name());
    }
}
