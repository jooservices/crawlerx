<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Oppai;

use JOOservices\CrawlerX\Adapters\Oppai\OppaiCrawler;
use JOOservices\CrawlerX\Adapters\Oppai\Site;
use JOOservices\CrawlerX\Adapters\S1\Site as S1Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class OppaiTest extends TestCase
{
    private const BASE = 'https://oppai-av.com';

    private const CDN = 'https://cdn.up-timely.com/image/20/content/88220/';

    public function test_detail_fields_from_real_capture(): void
    {
        $result = $this->detail('PPPE459');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/PPPE459', $result->url);
        self::assertSame('PPPE459', $movie['external_id']);
        self::assertSame('PPPE459', $movie['code']);
        self::assertSame('会いに行けちゃう現役セクキャバ嬢 超有名優良店在籍!人気嬢AVデビュー', $movie['title']);
        self::assertStringStartsWith('超有名セクキャバに勤務するHカップ巨乳がAVデビュー！', $movie['description']);
        self::assertStringEndsWith('大人の巨乳お姉さんから目が離せない！', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame(self::CDN . 'aCukwTfajblUD5OFu74JRXtOm1DsS4gQVxLVtfuC.jpg', $movie['cover_url']);
        self::assertCount(13, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . 'zIFVH7qVkk0TG7CqR3N76Se4iMRpLu9hPbG96IDH.jpg', $movie['screenshots'][12]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('872737', $movie['performers'][0]['external_id']);
        self::assertSame('西条ありさ', $movie['performers'][0]['name']);
        self::assertSame('西条ありさ', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/872737', $movie['performers'][0]['url']);
        self::assertSame(['顔射', 'パイズリ', 'デビュー作品', '潮吹き', '巨乳'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('OPPAI', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => 'OPPAI', 'url' => self::BASE . '/works/list/label/7559', 'external_id' => '7559']], $meta['label']);
        self::assertSame([['name' => '2026年11月17日', 'url' => self::BASE . '/works/list/date/2026-11-17', 'external_id' => '2026-11-17']], $meta['release_links']);
        self::assertCount(5, $meta['genres']);
        self::assertSame('329', $meta['genres'][0]['external_id']);
        self::assertSame(self::BASE . '/works/list/genre/215', $meta['genres'][4]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'PPPE459', 'duration' => 120, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/VH46HhkD-9skJg-Liea_DGKcAMHQkoKoIwKue_xwAijAOkAeUUU8HdtTevl071/pppe00459dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . 'sample_video_image_file/Fz2CO3q3iFuWZJSIiIPdG8qGgPHbNkWrxhSJKGwX.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=pppe00459/WILLaffi-014', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 3,498円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame('レーベル OPPAI', $meta['source_fields']['レーベル']);
        self::assertSame('女優 西条ありさ', $meta['source_fields']['女優']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        self::assertSame('監督', $meta['source_fields']['監督']);
        self::assertCount(9, $meta['source_fields']);
        foreach (['PPBD304', 'PPBD324', 'PPPE442', 'PPPE453', 'PPPE457', 'PPPE460'] as $recommended) {
            self::assertStringNotContainsString($recommended, (string) json_encode($movie));
        }
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('oppai/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('oppai/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(7, $result->items);
        self::assertSame(self::BASE . '/works/detail/PPPE459', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('PPPE459', $movie['external_id']);
        self::assertSame('PPPE459', $movie['code']);
        self::assertSame('会いに行けちゃう現役セクキャバ嬢 超有名優良店在籍!人気嬢AVデビュー', $movie['title']);
        self::assertSame(self::CDN . 'lob82obYHElMG7MhrfsxngnZKuHcAQSNWqqDFGWc.jpg', $movie['cover_url']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame('872737', $movie['performers'][0]['external_id']);
        self::assertSame('西条ありさ', $movie['performers'][0]['name']);
        self::assertSame(7, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-17', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-17', $movie['metadata']['release_group']['url']);
        self::assertSame('全7作品中 1 〜 7 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年11月17日', $movie['metadata']['search_key']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(121, $result->pagination->lastPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_genre_listing_paginates_and_strips_tracking_query(): void
    {
        $url = self::BASE . '/works/list/genre/315';
        $result = (new Listing($this->clientWithFixture('oppai/list-genre-315.html'), $this->config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(30, $result->items);
        self::assertSame(self::BASE . '/works/detail/PPPE459', $result->items[0]->url);
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('PPPE391', $movie['external_id']);
        self::assertSame('もっちもちツルツルまっしろJcup しらたま AVデビュー！', $movie['title']);
        self::assertSame('しらたま', $movie['performers'][0]['name']);
        self::assertNull($movie['date']);
        self::assertSame('デビュー作品', $movie['metadata']['search_key']);
        self::assertSame('全71作品中 1 〜 30 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame($url . '?page=2', $result->pagination->nextUrl);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('oppai/actress-872737.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/872737'));
        $person = $result->meta['performer'];
        $image = 'https://cdn.up-timely.com/image/20/actress_main/872737/pOCCEnjAFh0Q7JtbsMFv0bHv9SYC2HrtvrqLjNLt.jpg';
        self::assertSame('872737', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/872737', $person['url']);
        self::assertSame('SAIJO ARISA', $person['name']);
        self::assertSame('西条ありさ', $person['name_japanese']);
        self::assertSame(['西条ありさ', 'SAIJO ARISA'], $person['aliases']);
        self::assertNull($person['birth_date_raw']);
        self::assertSame('155cm', $person['height_raw']);
        self::assertSame('B93cm (-) W56cm H88cm', $person['size_raw']);
        self::assertSame(['身長' => '155cm', '3サイズ' => 'B93cm (-) W56cm H88cm', '出身地' => '東京', '趣味' => '美容/お酒'], $person['raw_profile']);
        self::assertSame($image, $person['profile_image_url']);
        self::assertSame([$image], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertCount(1, $person['metadata']['works']);
        self::assertSame('PPPE459', $person['metadata']['works'][0]['external_id']);
        self::assertSame(self::BASE . '/works/detail/PPPE459', $person['metadata']['works'][0]['url']);
        self::assertSame('会いに行けちゃう現役セクキャバ嬢 超有名優良店在籍!人気嬢AVデビュー', $person['metadata']['works'][0]['name']);
        self::assertSame(self::CDN . 'lob82obYHElMG7MhrfsxngnZKuHcAQSNWqqDFGWc.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('oppai');
        self::assertInstanceOf(OppaiCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-11-17' => CrawlType::Listing,
            '/works/list/genre/315' => CrawlType::Listing,
            '/works/list/label/7559' => CrawlType::Listing,
            '/works/list/series/1' => CrawlType::Listing,
            '/works/detail/PPPE459' => CrawlType::Detail,
            '/actress/detail/872737' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('oppai', $crawler->name());
    }

    public function test_shared_base_leaves_s1_output_unchanged(): void
    {
        $result = (new Detail($this->clientWithFixture('s1/detail-SNOS524.html'), S1Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/detail/SNOS524'));
        self::assertArrayNotHasKey('label', $result->meta['movie']['metadata']);
        self::assertSame('イナバール', $result->meta['movie']['metadata']['director']);
    }

    private function detail(string $code): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('oppai/detail-' . $code . '.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }

    private function config(): SiteConfig
    {
        return Site::config();
    }
}
