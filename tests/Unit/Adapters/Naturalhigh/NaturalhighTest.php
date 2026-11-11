<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Naturalhigh;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Adapters\Naturalhigh\NaturalhighCrawler;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Types\Detail;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Types\Listing;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Tests\TestCase;
use Nyholm\Psr7\Response;

final class NaturalhighTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_listing_fields_and_real_pagination_from_captured_page_one(): void
    {
        $result = (new Listing($this->clientWithFixture('naturalhigh/listing-page-1.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.naturalhigh.co.jp/all/', type: CrawlType::Listing));

        self::assertCount(36, $result->items);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(127, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame('https://www.naturalhigh.co.jp/all/?sf_paged=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);

        $movie = $result->items[0]->meta['movie'];
        self::assertSame('https://www.naturalhigh.co.jp/all/nhdtc-239/', $result->items[0]->url);
        self::assertSame('NHDTC-239', $movie['external_id']);
        self::assertSame('NHDTC-239', $movie['code']);
        self::assertSame('満員バスOK娘スペシャル 絶対NGの黒髪巨乳コンビニ店員を連日羞恥で大量ぶっかけまでOKさせろ', $movie['title']);
        self::assertSame('https://www.naturalhigh.co.jp/wp-content/uploads/2026/11/NHDTC-239_01-1.jpg', $movie['cover_url']);
        self::assertSame([], $movie['performers']);
    }

    public function test_listing_page_two_reads_the_current_page_and_real_next_and_last_pages(): void
    {
        $result = (new Listing($this->clientWithFixture('naturalhigh/listing-page-2.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.naturalhigh.co.jp/all/?sf_paged=2', type: CrawlType::Listing));

        self::assertCount(36, $result->items);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(127, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame('https://www.naturalhigh.co.jp/all/?sf_paged=3', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);

        $movie = $result->items[0]->meta['movie'];
        self::assertSame('https://www.naturalhigh.co.jp/all/nhdta-402ai/', $result->items[0]->url);
        self::assertSame('NHDTA-402AI', $movie['external_id']);
        self::assertSame('NHDTA-402AI', $movie['code']);
        self::assertSame('【AIリマスター】生中痴漢7', $movie['title']);
        self::assertSame('https://www.naturalhigh.co.jp/wp-content/uploads/2026/10/NHDTA-402AI_H4.jpg', $movie['cover_url']);
    }

    public function test_listing_page_two_markup_wins_when_request_url_is_page_one(): void
    {
        $result = (new Listing($this->clientWithFixture('naturalhigh/listing-page-2.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.naturalhigh.co.jp/all/', type: CrawlType::Listing));

        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame('https://www.naturalhigh.co.jp/all/?sf_paged=3', $result->pagination->nextUrl);
    }

    public function test_listing_leaves_last_page_unknown_when_pagination_does_not_show_it(): void
    {
        $html = preg_replace('/<a class="last"[^>]*>.*?<\/a>/', '', $this->loadFixture('naturalhigh/listing-page-2.html'));
        self::assertIsString($html);
        $result = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://www.naturalhigh.co.jp/all/', type: CrawlType::Listing));

        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertNull($result->pagination->lastPage);
    }

    public function test_detail_maps_every_label_and_extracts_only_the_work_images_in_page_order(): void
    {
        $result = (new Detail($this->clientWithFixture('naturalhigh/detail-nhdtc-271.html')))
            ->execute(new CrawlRequestDto(url: 'https://www.naturalhigh.co.jp/all/nhdtc-271/', type: CrawlType::Detail));
        $movie = $result->meta['movie'];

        self::assertSame('NHDTC-271', $movie['external_id']);
        self::assertSame('NHDTC-271', $movie['code']);
        self::assertSame('電車の中でインナーを抜き取られ透け巨乳を見られる羞恥で抵抗できないシアーシャツ女子 SP ピンク乳首JDを連日シースルー露出で潮吹きドM開発', $movie['title']);
        self::assertSame('2026-11-19', $movie['date']);
        self::assertSame(200, $movie['duration']);
        self::assertSame('色白女子大生を連日シースルー露出で堕とす特別編! 路線バスでインナーを奪われスケスケ羞恥! 悲劇は終わらずバイト先にまで現れた痴○男! 出会うたび恥ずかしい姿にさせられピンク乳首ビン勃ち! 突き刺さるような視線を浴びてイキ乱れ! 遂には上下シースルー服にさせられ言いなり堕ち!!', $movie['description']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['巨乳', '潮吹き', 'ハイビジョン', '羞恥', '巨乳フェチ', 'ぶっかけ'], $movie['tags']);

        $metadata = $movie['metadata'];
        self::assertSame('NATURAL HIGH', $metadata['maker']);
        self::assertSame('SYUCHI', $metadata['label']);
        self::assertSame('電車の中でインナーを抜き取られ透け巨乳を見られる羞恥で抵抗できないシアーシャツ女子', $metadata['series']);
        self::assertSame('KANSUKE', $metadata['director']);
        self::assertSame(
            ['作品紹介', '監督', '発売日', 'シリーズ作品', '収録時間', '品番', 'レーベル', 'ジャンル'],
            array_keys($metadata['source_fields']),
        );
        self::assertSame($movie['description'], $metadata['source_fields']['作品紹介']);
        self::assertSame('KANSUKE', $metadata['source_fields']['監督']);
        self::assertSame('2026/11/19', $metadata['source_fields']['発売日']);
        self::assertSame('電車の中でインナーを抜き取られ透け巨乳を見られる羞恥で抵抗できないシアーシャツ女子', $metadata['source_fields']['シリーズ作品']);
        self::assertSame('200分', $metadata['source_fields']['収録時間']);
        self::assertSame('NHDTC-271', $metadata['source_fields']['品番']);
        self::assertSame('SYUCHI', $metadata['source_fields']['レーベル']);
        self::assertSame('巨乳 / 潮吹き / ハイビジョン / 羞恥 / 巨乳フェチ / ぶっかけ', $metadata['source_fields']['ジャンル']);

        $expectedImages = [
            ['https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/NHDTC-271_RGB.jpg', 'https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/NHDTC-271_01-1.jpg'],
            ...array_map(
                static fn(int $slot): array => [
                    sprintf('https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/NHDTC-271L_%02d.jpg', $slot),
                    sprintf('https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/NHDTC-271_%02d.jpg', $slot),
                ],
                range(1, 20),
            ),
        ];
        self::assertSame($expectedImages[0][0], $movie['cover_url']);
        self::assertCount(21, $movie['screenshots']);
        foreach ($expectedImages as $index => [$url, $thumbnailUrl]) {
            self::assertSame($url, $movie['screenshots'][$index]['url'], 'Image slot ' . ($index + 1));
            self::assertSame($thumbnailUrl, $movie['screenshots'][$index]['thumbnail_url'], 'Image thumbnail ' . ($index + 1));
            self::assertSame($index + 1, $movie['screenshots'][$index]['metadata']['position']);
        }
    }

    public function test_detail_skips_duplicate_image_urls_and_preserves_first_thumbnail_and_position(): void
    {
        $html = str_replace(
            '<div class="p-style__gallery">',
            '<div class="p-style__gallery"><a href="https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/NHDTC-271_RGB.jpg"><img src="https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/duplicate-thumbnail.jpg"></a>',
            $this->loadFixture('naturalhigh/detail-nhdtc-271.html'),
        );
        $result = (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://www.naturalhigh.co.jp/all/nhdtc-271/', type: CrawlType::Detail));
        $images = $result->meta['movie']['screenshots'];

        self::assertCount(21, $images);
        self::assertSame('https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/NHDTC-271_RGB.jpg', $images[0]['url']);
        self::assertSame('https://www.naturalhigh.co.jp/wp-content/uploads/2026/09/NHDTC-271_01-1.jpg', $images[0]['thumbnail_url']);
        self::assertSame(range(1, 21), array_column(array_column($images, 'metadata'), 'position'));
    }

    public function test_only_catalog_listing_and_work_detail_urls_are_detected(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('naturalhigh');
        self::assertInstanceOf(NaturalhighCrawler::class, $crawler);

        self::assertSame(CrawlType::Listing, $crawler->detectUrl('https://www.naturalhigh.co.jp/all/')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl('https://www.naturalhigh.co.jp/all/?sf_paged=2')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl('https://www.naturalhigh.co.jp/all/nhdtc-271/')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl('https://www.naturalhigh.co.jp/all/nhdta-402ai/')->crawlType);
        self::assertFalse($crawler->detectUrl('https://www.naturalhigh.co.jp/series/374bbe57/')->matched);
        self::assertFalse($crawler->detectUrl('https://example.com/all/')->matched);
    }

    public function test_custom_http_headers_keep_the_required_age_gate_cookie(): void
    {
        $fixture = $this->loadFixture('naturalhigh/listing-page-1.html');
        $fake = ClientBuilder::fake();
        $fake->respond('GET', '/all/', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $fixture),
        ));
        $manifests = new FileAdapterManifestRegistry();
        $manifest = $manifests->get('naturalhigh');
        self::assertNotNull($manifest);
        $adapter = new NaturalhighCrawler(new ClientFactory(), $manifest);

        (new AdapterExecutor())->execute($adapter, new CrawlRequestDto(
            url: 'https://www.naturalhigh.co.jp/all/',
            type: CrawlType::Listing,
            options: new CrawlOptionsDto(http: new HttpOptionsDto(headers: ['Accept-Language' => 'ja,en;q=0.8'])),
        ));

        $recorded = $fake->recorded();
        self::assertCount(1, $recorded);
        self::assertSame('age_gate=18', $recorded[0]->request->getHeaderLine('Cookie'));
        self::assertSame('ja,en;q=0.8', $recorded[0]->request->getHeaderLine('Accept-Language'));
    }
}
