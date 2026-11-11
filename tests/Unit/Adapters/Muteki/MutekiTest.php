<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Muteki;

use JOOservices\CrawlerX\Adapters\Muteki\MutekiCrawler;
use JOOservices\CrawlerX\Adapters\Muteki\Site;
use JOOservices\CrawlerX\Adapters\Muteki\Types\Detail;
use JOOservices\CrawlerX\Adapters\Muteki\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class MutekiTest extends TestCase
{
    private const BASE = 'https://www.mutekimuteki.com';

    private const THUMBNAIL = 'https://cdn.up-timely.com/muteki/works/tek100/tek102-ps.jpg';

    private const COVER = 'https://cdn.up-timely.com/muteki/works/tek100/tek102-pl_new.jpg';

    public function test_listing_fields_and_pagination_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('muteki/listing-page1.html')))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/release/'));

        self::assertCount(1, $result->items);
        $item = $result->items[0];
        $movie = $item->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/tek102/', $item->url);
        self::assertSame('TEK102', $movie['external_id']);
        self::assertSame('TEK102', $movie['code']);
        self::assertSame('Flowing river', $movie['title']);
        self::assertSame('2025-06-03', $movie['date']);
        self::assertSame(self::THUMBNAIL, $movie['cover_url']);
        self::assertSame(['紫堂るい'], array_column($movie['performers'], 'name'));
        self::assertSame(['紫堂るい'], array_column($movie['performers'], 'name_japanese'));
        self::assertSame([self::BASE . '/actress/detail/1102441/'], array_column($movie['performers'], 'url'));
        self::assertSame(['1102441'], array_column($movie['performers'], 'external_id'));
        self::assertSame('MUTEKI', $movie['metadata']['maker']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
    }

    public function test_detail_extracts_every_labelled_field_and_work_image(): void
    {
        $result = (new Detail($this->clientWithFixture('muteki/detail-tek102.html')))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/tek102/'));
        $movie = $result->meta['movie'];

        self::assertSame(self::BASE . '/works/detail/tek102/', $result->url);
        self::assertSame('TEK102', $movie['external_id']);
        self::assertSame('TEK102', $movie['code']);
        self::assertSame('Flowing river', $movie['title']);
        self::assertStringStartsWith('弱冠8歳で芸能界に入り', $movie['description']);
        self::assertStringEndsWith('MUTEKIへ… SNSアカウント@rui_shidoも要チェック。', $movie['description']);
        self::assertSame('2025-06-03', $movie['date']);
        self::assertSame(140, $movie['duration']);
        self::assertSame(self::COVER, $movie['cover_url']);
        self::assertSame([[
            'url' => self::COVER,
            'thumbnail_url' => self::THUMBNAIL,
            'metadata' => ['position' => 0],
        ]], $movie['screenshots']);
        self::assertSame(['紫堂るい'], array_column($movie['performers'], 'name'));
        self::assertSame(['紫堂るい'], array_column($movie['performers'], 'name_japanese'));
        self::assertSame([self::BASE . '/actress/detail/1102441/'], array_column($movie['performers'], 'url'));
        self::assertSame(['1102441'], array_column($movie['performers'], 'external_id'));

        $metadata = $movie['metadata'];
        self::assertSame('MUTEKI', $metadata['maker']);
        self::assertSame(self::THUMBNAIL, $metadata['cover_thumbnail_url']);
        self::assertSame([
            ['format' => 'Blu-ray', 'code' => '9TEK102', 'duration' => 140],
            ['format' => 'DVD', 'code' => 'TEK102', 'duration' => 140],
        ], $metadata['formats']);
        self::assertSame([[
            'name' => '2025/6/3',
            'url' => self::BASE . '/works/list/year/2025/',
            'external_id' => '2025',
        ]], $metadata['release_links']);
        self::assertSame(['FANZA独占販売', '定価5,698円 (税込)', '定価4,598円 (税込)'], $metadata['prices']);
        self::assertCount(3, $metadata['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=tek00102/WILLaffi-016', $metadata['purchase_links'][0]['url']);
        self::assertSame('https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=9tek102/WILLaffi-016', $metadata['purchase_links'][1]['url']);
        self::assertSame('https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=tek102/WILLaffi-016', $metadata['purchase_links'][2]['url']);
        self::assertSame('https://cc3001.dmm.co.jp/litevideo/freepv/t/tek/tek00102/tek00102_dm_w.mp4', $metadata['sample_video_url']);
        self::assertSame([
            '出演者' => '出演者 紫堂るい',
            '発売日' => '発売日 2025/6/3',
            '品番' => '品番 Blu-ray9TEK102 DVDTEK102',
            '収録時間' => '収録時間 Blu-ray140分 DVD140分',
        ], $metadata['source_fields']);
    }

    public function test_urls_are_detected_for_listing_and_detail(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('muteki');

        self::assertInstanceOf(MutekiCrawler::class, $crawler);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/works/list/release/')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/works/detail/tek102/')->crawlType);
        self::assertFalse($crawler->detectUrl(self::BASE . '/actress/detail/1102441/')->matched);
        self::assertSame(self::BASE, Site::config()->baseUrl);
        self::assertSame('MUTEKI', Site::config()->maker);
        self::assertTrue(Site::config()->plainPerformers);
    }
}
