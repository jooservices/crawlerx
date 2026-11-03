<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Shared;

use Faker\Factory;
use JOOservices\CrawlerX\Adapters\S1\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Fields;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

class MakerCmsTest extends TestCase
{
    public function test_movie_fields_from_real_capture_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('s1/detail-SNOS524.html'), Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/detail/SNOS524'));
        $movie = $result->meta['movie'];
        self::assertSame('https://s1s1s1.com/works/detail/SNOS524', $result->url);
        self::assertSame('SNOS524', $movie['external_id']);
        self::assertSame('SNOS524', $movie['code']);
        self::assertStringStartsWith('新人NO.1 STYLE', $movie['title']);
        self::assertStringStartsWith('26歳 独身。', $movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame(210, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/2/content/88278/NWr2TDEoWbO1VVD0rSrb8580yT1ESaTvEWeLZ4kE.jpg', $movie['cover_url']);
        self::assertCount(10, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertStringEndsWith('/GAfqtEeixWYPOScqadnViYJV4lNG1baB8tjs9Z37.jpg', $movie['screenshots'][9]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('874653', $movie['performers'][0]['external_id']);
        self::assertSame('雪野緋七', $movie['performers'][0]['name']);
        self::assertSame('雪野緋七', $movie['performers'][0]['name_japanese']);
        self::assertSame('https://s1s1s1.com/actress/detail/874653', $movie['performers'][0]['url']);
        self::assertSame(['お姉さん', '巨乳', 'ハメ撮り', '3P、4P', 'デビュー作品', 'Blu-ray（ブルーレイ）'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('S1 NO.1 STYLE', $meta['maker']);
        self::assertSame('イナバール', $meta['director']);
        self::assertArrayNotHasKey('label', $meta);
        self::assertSame([['name' => '新人NO.1STYLE', 'url' => 'https://s1s1s1.com/works/list/series/414', 'external_id' => '414']], $meta['series']);
        self::assertSame('https://s1s1s1.com/works/list/date/2026-11-24', $meta['release_links'][0]['url']);
        self::assertSame('2026年11月24日', $meta['release_links'][0]['name']);
        self::assertSame('376', $meta['genres'][0]['external_id']);
        self::assertSame('https://s1s1s1.com/works/list/genre/376', $meta['genres'][0]['url']);
        self::assertSame([
            ['format' => 'DVD', 'code' => 'SNOS524', 'duration' => 210, 'price' => '4,048円（税込）'],
            ['format' => 'Blu-ray', 'code' => '9SNOS524', 'duration' => 210, 'price' => '5,148円（税込）'],
        ], $meta['formats']);
        self::assertStringEndsWith('/snos00524dm.mp4', $meta['sample_video_url']);
        self::assertStringEndsWith('/nuZPWtlSvW75bB8rwtp4jEsXB5Nfvg33KewTcjpF.jpg', $meta['sample_video_poster_url']);
        self::assertCount(3, $meta['purchase_links']);
        self::assertStringContainsString('cid=snos00524', $meta['purchase_links'][0]['url']);
        self::assertStringContainsString('動画を購入', $meta['purchase_links'][0]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertCount(8, $meta['source_fields']);
    }

    public function test_listing_fields_and_date_walker(): void
    {
        $base = 'https://s1s1s1.com';
        $client = $this->clientWithMappedResponses([
            $base . '/works/date' => ['body' => $this->loadFixture('s1/dates.html')],
            $base . '/works/list/date/2026-11-24' => ['body' => $this->loadFixture('s1/list-2026-11-24.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: $base . '/works/date'));
        self::assertCount(23, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame($base . '/works/detail/SNOS524', $result->items[0]->url);
        self::assertSame('SNOS524', $movie['external_id']);
        self::assertSame('SNOS524', $movie['code']);
        self::assertNotEmpty($movie['title']);
        self::assertStringEndsWith('/fF0pKmP4Hwxheg7ITLJaDGcuUvkE080dwYIUolO7.jpg', $movie['cover_url']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame('雪野緋七', $movie['performers'][0]['name']);
        self::assertSame('874653', $movie['performers'][0]['external_id']);
        self::assertSame($base . '/actress/detail/874653', $movie['performers'][0]['url']);
        self::assertSame(23, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-24', $movie['metadata']['release_group']['date']);
        self::assertSame($base . '/works/list/date/2026-11-24', $movie['metadata']['release_group']['url']);
        self::assertSame('全23作品中 1 〜 23 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertNull($movie['metadata']['badge']);
        self::assertSame(27, $result->items[20]->meta['movie']['metadata']['performer_count']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame($base . '/works/date?page=2', $result->pagination->nextUrl);
        $direct = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: $base . '/works/list/date/2026-11-24'));
        self::assertFalse($direct->pagination->hasNextPage);
    }

    public function test_actress_both_names_bio_images_social_and_works(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('s1/actress-22486.html'), Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/actress/detail/22486'));
        $person = $result->meta['performer'];
        self::assertSame('22486', $person['external_id']);
        self::assertSame('https://s1s1s1.com/actress/detail/22486', $person['url']);
        self::assertSame('KAWAKITA SAIKA', $person['name']);
        self::assertSame('河北彩花', $person['name_japanese']);
        self::assertSame(['河北彩花', 'KAWAKITA SAIKA'], $person['aliases']);
        self::assertSame('1999-04-24', $person['birth_date_raw']);
        self::assertSame('169cm', $person['height_raw']);
        self::assertSame('B87cm (-) W57cm H86cm', $person['size_raw']);
        self::assertSame('映画鑑賞,ミュージカル鑑賞', $person['raw_profile']['趣味']);
        self::assertSame('1999年4月24日', $person['raw_profile']['誕生日']);
        self::assertStringEndsWith('/y1ps5D8zGSjKz7s2CrajZ4AVo52UJpSZI2xveEQp.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame('https://x.com/Saika_Kawakita', $person['metadata']['social_links'][0]['url']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertNotEmpty($person['metadata']['works']);
        self::assertNotEmpty($person['metadata']['works'][0]['name']);
        self::assertNotEmpty($person['metadata']['works'][0]['cover_url']);
        self::assertStringContainsString('/works/detail/', $person['metadata']['works'][0]['url']);
        self::assertNotEmpty($person['metadata']['work_range']);
        $empty = (new PerformerDetail($this->clientWithFixture('s1/actress-874653.html'), Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/actress/detail/874653'));
        self::assertSame([], $empty->meta['performer']['raw_profile']);
        self::assertNull($empty->meta['performer']['birth_date_raw']);
        self::assertSame('SNOS524', $empty->meta['performer']['metadata']['works'][0]['external_id']);
    }

    public function test_second_host_needs_only_configuration(): void
    {
        $faker = Factory::create();
        $config = new SiteConfig(baseUrl: 'https://' . $faker->domainName(), maker: $faker->company());
        $crawler = new class ($this->clientWithFixture('s1/detail-SNOS524.html'), $config) extends FamilyCrawler {
            public function __construct(CrawlHttpClient $client, private readonly SiteConfig $config)
            {
                parent::__construct(new ClientFactory());
                $this->client = $client;
            }

            public function name(): string
            {
                return $this->config->maker;
            }

            protected function siteConfig(): SiteConfig
            {
                return $this->config;
            }
        };
        $result = $crawler->detail(new CrawlRequestDto(url: $config->baseUrl . '/works/detail/SNOS524'));
        self::assertSame($config->baseUrl . '/works/detail/SNOS524', $result->url);
        self::assertSame($config->maker, $result->meta['movie']['metadata']['maker']);
        self::assertSame($config->baseUrl . '/assets/a.jpg', $config->absolute('/assets/a.jpg'));
        self::assertNull((new Fields($config))->date('2026-02-31'));
    }

    public function test_missing_movie_fields_are_rejected(): void
    {
        $this->expectException(CrawlParseException::class);
        (new Detail($this->clientWithFixture('s1/dates.html'), Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/detail/SNOS524'));
    }
    public function test_invalid_entity_routes_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Site::config()->entityId('https://s1s1s1.com/works/detail/', '/works/detail/');
    }

    public function test_wrong_entity_routes_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Site::config()->entityId('https://s1s1s1.com/top', '/works/detail/');
    }

    public function test_empty_listing_is_rejected(): void
    {
        $this->expectException(CrawlParseException::class);
        (new Listing($this->clientWithFixture('s1/actress-874653.html'), Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/list/date/2026-11-24'));
    }

    public function test_exhausted_date_index_is_rejected(): void
    {
        $this->expectException(CrawlParseException::class);
        (new Listing($this->clientWithFixture('s1/dates.html'), Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/date', page: 100000));
    }

}
