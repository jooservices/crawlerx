<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Deeps;

use JOOservices\CrawlerX\Adapters\Deeps\DeepsCrawler;
use JOOservices\CrawlerX\Adapters\Deeps\Site;
use JOOservices\CrawlerX\Adapters\Deeps\Types\Detail;
use JOOservices\CrawlerX\Adapters\Deeps\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class DeepsTest extends TestCase
{
    private const ITEM = 'https://deeps.net/item/';

    public function test_detail_without_cast_or_director_from_real_capture(): void
    {
        $result = $this->detail('mmgo-020');
        $movie = $result->meta['movie'];
        self::assertSame(self::ITEM . 'detail.php?mmgo-020', $result->url);
        self::assertSame('movie', $result->entityType);
        self::assertSame('mmgo-020', $movie['external_id']);
        self::assertSame('MMGO-020', $movie['code']);
        self::assertStringStartsWith('素人ナンパAV No.１宣言！ＭＭ号＆マジックミラー便 2024年下半期', $movie['title']);
        self::assertStringEndsWith('プレミアム作品集 ２枚組10時間永久保存版スペシャル!!', $movie['title']);
        self::assertStringStartsWith('お待たせしました！素人ナンパの殿堂マジックミラーシリーズBESTの登場です！', $movie['description']);
        self::assertStringEndsWith('２枚組10時間の永久保存版です！', $movie['description']);
        self::assertSame('2026-11-03', $movie['date']);
        self::assertSame(600, $movie['duration']);
        self::assertSame(self::ITEM . 'img/mmgo/mmgo-020.jpg', $movie['cover_url']);
        self::assertSame(['素人', '素人顔出し', '【ベスト・総集編】'], $movie['tags']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('本物素人娘50人', $movie['performers'][0]['name']);
        self::assertSame(self::ITEM . 'index.php?w_本物素人娘50人', $movie['performers'][0]['url']);
        self::assertCount(10, $movie['screenshots']);
        self::assertSame(self::ITEM . 'sample/mmgo/mmgo-020-0001.jpg', $movie['screenshots'][0]['url']);
        self::assertSame(self::ITEM . 'img.php?mmgo-020_0001', $movie['screenshots'][0]['metadata']['viewer_url']);
        self::assertSame(self::ITEM . 'sample/mmgo/mmgo-020-0010.jpg', $movie['screenshots'][9]['url']);
        self::assertSame(self::ITEM . 'img.php?mmgo-020_0010', $movie['screenshots'][9]['metadata']['viewer_url']);
        $meta = $movie['metadata'];
        self::assertSame("DEEP'S", $meta['maker']);
        self::assertArrayNotHasKey('director', $meta);
        self::assertSame('ザ・マジックミラー／MM号（旧称：マジックミラー号）, マジックミラー便／MM便', $meta['series']);
        self::assertSame(['素人女性'], $meta['types']);
        self::assertSame(['amount' => 3180.0, 'currency' => 'JPY', 'label' => '3,180 円（税抜）'], $meta['price']);
        self::assertSame('2026.11.3', $meta['release_date_raw']);
        self::assertSame('https://www.dmm.co.jp/litevideo/-/part/=/cid=mmgo020/size=1280_720/', $meta['sample_video_url']);
        self::assertSame([
            ['name' => 'ＦＡＮＺＡ動画', 'url' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=mmgo00020/?utm_medium=dmm_affiliate&utm_source=deeps-001'],
            ['name' => 'ＦＡＮＺＡ通販', 'url' => 'http://www.dmm.co.jp/mono/dvd/-/detail/=/cid=mmgo020/deeps-001'],
        ], $meta['purchase_links']);
        self::assertSame([
            '発売日' => '2026.11.3',
            '収録時間' => '600分',
            '監督' => null,
            '品番/価格' => 'MMGO-020 / 3,180 円（税抜）',
            '出演' => '本物素人娘50人',
            'シリーズ' => 'ザ・マジックミラー／MM号（旧称：マジックミラー号） マジックミラー便／MM便',
            'タイプ・職業' => '素人女性',
            'カテゴリ' => '素人 素人顔出し 【ベスト・総集編】',
        ], $meta['source_fields']);
        self::assertStringNotContainsString('dvmm-196', (string) json_encode($movie));
    }

    public function test_detail_with_cast_and_director_but_no_series_from_real_capture(): void
    {
        $movie = $this->detail('gdjp-012')->meta['movie'];
        self::assertSame('gdjp-012', $movie['external_id']);
        self::assertSame('GDJP-012', $movie['code']);
        self::assertSame('ガチ昼呑みはしご酒 ～シラフとヘベレケ、ギャップ検証ドキュメント～ 飲んで、笑って、酔って、本音!?【ギャル編】', $movie['title']);
        self::assertStringStartsWith('お酒大好きな人気AV女優の、酔ったエッチな姿を観てみたい', $movie['description']);
        self::assertSame('2026-10-20', $movie['date']);
        self::assertSame(160, $movie['duration']);
        self::assertSame(self::ITEM . 'img/gdjp/gdjp-012.jpg', $movie['cover_url']);
        self::assertSame(['痴女'], $movie['tags']);
        self::assertSame(['鳳カレン', '若菜めあり'], array_column($movie['performers'], 'name'));
        self::assertSame(self::ITEM . 'index.php?w_若菜めあり', $movie['performers'][1]['url']);
        self::assertCount(10, $movie['screenshots']);
        self::assertSame(self::ITEM . 'sample/gdjp/gdjp-012-0001.jpg', $movie['screenshots'][0]['url']);
        $meta = $movie['metadata'];
        self::assertSame('ゴールデン', $meta['director']);
        self::assertArrayNotHasKey('series', $meta);
        self::assertSame(['ギャル'], $meta['types']);
        self::assertSame(3180.0, $meta['price']['amount']);
        self::assertSame('2026.10.20', $meta['release_date_raw']);
        self::assertSame('https://www.dmm.co.jp/litevideo/-/part/=/cid=gdjp012/size=1280_720/', $meta['sample_video_url']);
        self::assertSame('ＦＡＮＺＡ通販', $meta['purchase_links'][1]['name']);
        self::assertSame('ゴールデン', $meta['source_fields']['監督']);
        self::assertSame('鳳カレン 若菜めあり', $meta['source_fields']['出演']);
        self::assertNull($meta['source_fields']['シリーズ']);
        self::assertSame('GDJP-012 / 3,180 円（税抜）', $meta['source_fields']['品番/価格']);
        self::assertCount(8, $meta['source_fields']);
    }

    public function test_catalog_listing_cards_and_pagination_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('deeps/list.html')))->execute(new CrawlRequestDto(url: self::ITEM));
        self::assertSame(self::ITEM, $result->url);
        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(34, $result->items);
        $first = $result->items[0];
        self::assertSame(self::ITEM . 'detail.php?dvmm-458', $first->url);
        $movie = $first->meta['movie'];
        self::assertSame('dvmm-458', $movie['external_id']);
        self::assertSame('DVMM-458', $movie['code']);
        self::assertNull($movie['title']);
        self::assertSame(self::ITEM . 'img/dvmm/dvmm-458.jpg', $movie['cover_url']);
        self::assertStringStartsWith('街行く夫婦に声をかけ特設マジックミラースタジオにご案内！', $movie['description']);
        self::assertStringEndsWith('「中に出して～！」と托卵懇願！', $movie['description']);
        self::assertSame('shinsaku', $movie['metadata']['release_class']);
        self::assertSame(2662, $movie['metadata']['listing_total']);
        self::assertNull($result->items[11]->meta['movie']['description']);
        self::assertSame('dvmm-456', $result->items[11]->meta['movie']['external_id']);
        self::assertArrayNotHasKey('release_class', $result->items[33]->meta['movie']['metadata']);
        self::assertSame('dvmm-441', $result->items[33]->meta['movie']['external_id']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(53, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::ITEM . 'index.php?0__50_2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_series_listing_reads_the_page_from_the_url_and_stops_on_the_last_page(): void
    {
        $filter = 's_マジックミラー便／MM便';
        $first = (new Listing($this->clientWithFixture('deeps/list-series-mm-bin.html')))->execute(new CrawlRequestDto(url: self::ITEM . '?' . $filter));
        self::assertCount(48, $first->items);
        self::assertSame('mmgo-020', $first->items[0]->meta['movie']['external_id']);
        self::assertSame('junshinsaku', $first->items[1]->meta['movie']['metadata']['release_class']);
        self::assertSame(1, $first->page);
        self::assertSame(6, $first->pagination->lastPage);
        self::assertSame(self::ITEM . 'index.php?' . $filter . '_50_2', $first->pagination->nextUrl);

        $last = (new Listing($this->clientWithFixture('deeps/list-series-mm-bin.html')))->execute(new CrawlRequestDto(url: self::ITEM . 'index.php?' . $filter . '_50_6'));
        self::assertSame(6, $last->page);
        self::assertSame(6, $last->pagination->currentPage);
        self::assertNull($last->pagination->nextUrl);
        self::assertNull($last->pagination->nextPage);
        self::assertFalse($last->pagination->hasNextPage);
    }

    public function test_pages_without_data_are_rejected(): void
    {
        $this->expectException(CrawlParseException::class);
        (new Listing($this->clientWithHtml('<html><body><main><div class="main_column"></div></main></body></html>')))->execute(new CrawlRequestDto(url: self::ITEM));
    }

    public function test_detail_without_fields_is_rejected(): void
    {
        $this->expectException(CrawlParseException::class);
        (new Detail($this->clientWithHtml('<html><body><main></main></body></html>')))->execute(new CrawlRequestDto(url: self::ITEM . 'detail.php?none'));
    }

    public function test_site_helpers_ignore_foreign_urls(): void
    {
        self::assertNull(Site::externalId(self::ITEM));
        self::assertNull(Site::externalId(self::ITEM . 'detail.php?a=b&c=d'));
        self::assertNull(Site::pageNumber(self::ITEM . 'index.php?0__50_x'));
        self::assertSame(12, Site::pageNumber(self::ITEM . 'index.php?s_%E3%82%B7_50_12'));
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('deeps');
        self::assertInstanceOf(DeepsCrawler::class, $crawler);
        $expected = [
            '/item/' => CrawlType::Listing,
            '/item/index.php?0__50_2' => CrawlType::Listing,
            '/item/?s_マジックミラー便／MM便' => CrawlType::Listing,
            '/item/index.php?w_鳳カレン' => CrawlType::Listing,
            '/item/detail.php?mmgo-020' => CrawlType::Detail,
            '/item/detail.php?gdjp-012' => CrawlType::Detail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl('https://deeps.net' . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertSame(self::ITEM . 'detail.php?mmgo-020', $crawler->detectUrl(self::ITEM . 'detail.php?mmgo-020')->normalizedUrl);
        self::assertNull($crawler->detectUrl(self::ITEM)->normalizedUrl);
        self::assertFalse($crawler->detectUrl('https://deeps.net/item/detail.php')->matched);
        self::assertFalse($crawler->detectUrl('https://deeps.net/offering/')->matched);
        self::assertSame('deeps', $crawler->name());
    }

    private function detail(string $code): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('deeps/detail-' . $code . '.html')))->execute(new CrawlRequestDto(url: self::ITEM . 'detail.php?' . $code, type: CrawlType::Detail));
    }
}
