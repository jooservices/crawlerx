<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Tameikegoro;

use JOOservices\CrawlerX\Adapters\Tameikegoro\Site;
use JOOservices\CrawlerX\Adapters\Tameikegoro\TameikegoroCrawler;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class TameikegoroTest extends TestCase
{
    private const BASE = 'https://tameikegoro.jp';

    public function test_detail_extracts_every_field_from_real_capture(): void
    {
        $result = (new Detail($this->clientWithFixture('tameikegoro/detail-MFYD231.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/MFYD231'));
        self::assertSame(self::BASE . '/works/detail/MFYD231', $result->url);
        $movie = $result->meta['movie'];
        self::assertSame('MFYD231', $movie['external_id']);
        self::assertSame('神乳Jカップラウンジ嬢人妻・茉白 AV 出演ー。　美貌も体もエロもハイレベルな唯一無二の人妻', $movie['title']);
        self::assertSame('MFYD231', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/11/content/88184/oPGXIVIlkdEOntG56qSuT4kB6wLYkQhV6fxDTCMF.jpg', $movie['cover_url']);
        self::assertSame('銀座が育てたハイクラスJ神乳！美貌も、カラダも、SEXも、一つ上の存在ー。銀座ラウンジ嬢（人妻）・茉白AV出演！10年間第一線で活躍してきた美しさと華やかさ！ハリがあり美しい、究極のJカップ！貪りつきたいエロい乳首！滑らかなスレンダーくびれボディ！華やかで一つ上の彼女が見せた、積極的でスケベなSEX。キスの舌使いと積極性がめちゃくちゃエロいです。ちんぽで奥を突かれ、反り返る体とゆれる神乳がもうたまんない！この美貌と体でみなさんシコりまくって欲しいです！', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame([
            [
                'external_id' => '876448',
                'name' => '茉白',
                'name_japanese' => '茉白',
                'url' => 'https://tameikegoro.jp/actress/detail/876448',
                'profile_image_url' => null,
                'birth_date_raw' => null,
                'height_raw' => null,
                'size_raw' => null,
                'aliases' => [],
                'tags' => [],
                'raw_profile' => [],
                'metadata' => [],
            ],
        ], $movie['performers']);
        self::assertSame(['人妻・主婦', '巨乳', 'デビュー作品', '顔射', '熟女'], $movie['tags']);
        self::assertSame([
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/oPGXIVIlkdEOntG56qSuT4kB6wLYkQhV6fxDTCMF.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/Qz1Joulo3aZOH0Nyxns8gGJkv0rQrXXfJ7Z9Td2N.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/br2euypPDCyB8oyw3KfQ3DgKAkjLMrbFFsF1VHxn.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/Gn1wrc0zCANDmeoOdW8FozO8EETj0nM7dYYabTN5.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/rM4VmIPmg4imctk8sRw5hEGXMaicss5Sebf9J2a1.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/2LTCFetSp2CQcZ5FBswwqQuxQ6J5FkKWlS88pw7W.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/0r8bATOADFvanc3F7ICYmMZs7R5BwK8XrxBIydfg.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/wOSUIi41SQv9fOk07VqPcr8s2sNtBDUhuJPrNocy.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
            [
                'url' => 'https://cdn.up-timely.com/image/11/content/88184/SYCH13xov4KsspYGjpDTjEcl6Z26TfgZDyoYUN3y.jpg',
                'thumbnail_url' => null,
                'metadata' => [],
            ],
        ], $movie['screenshots']);
        self::assertSame('溜池ゴロー', $movie['metadata']['maker']);
        self::assertSame([
            [
                'name' => '人妻・主婦',
                'url' => 'https://tameikegoro.jp/works/list/genre/205',
                'external_id' => '205',
            ],
            [
                'name' => '巨乳',
                'url' => 'https://tameikegoro.jp/works/list/genre/215',
                'external_id' => '215',
            ],
            [
                'name' => 'デビュー作品',
                'url' => 'https://tameikegoro.jp/works/list/genre/315',
                'external_id' => '315',
            ],
            [
                'name' => '顔射',
                'url' => 'https://tameikegoro.jp/works/list/genre/329',
                'external_id' => '329',
            ],
            [
                'name' => '熟女',
                'url' => 'https://tameikegoro.jp/works/list/genre/206',
                'external_id' => '206',
            ],
        ], $movie['metadata']['genres']);
        self::assertSame([], $movie['metadata']['series']);
        self::assertSame([
            [
                'name' => '溜池ゴロー',
                'url' => 'https://tameikegoro.jp/works/list/label/5531',
                'external_id' => '5531',
            ],
        ], $movie['metadata']['label']);
        self::assertSame([
            [
                'name' => '2026年11月17日',
                'url' => 'https://tameikegoro.jp/works/list/date/2026-11-17',
                'external_id' => '2026-11-17',
            ],
        ], $movie['metadata']['release_links']);
        self::assertSame('豆沢豆太郎', $movie['metadata']['director']);
        self::assertSame([
            [
                'format' => 'DVD',
                'code' => 'MFYD231',
                'duration' => 120,
                'price' => '3,498円（税込）',
            ],
        ], $movie['metadata']['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/SiwMWDwx3kE-oUXzECCS3ulux8BhRStSf22vyNJRC4M_UjlYOl5bZ6xOpr4P3R/mfyd00231dm.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/11/content/88184/sample_video_image_file/1acruAM1y0AcuvbLTg6HyogyO7qGgmPJEEBDdeS8.jpg', $movie['metadata']['sample_video_poster_url']);
        self::assertSame([
            [
                'name' => '動画を購入 ダウンロード+ストリーミング',
                'url' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=mfyd00231/WILLaffi-030',
                'external_id' => 'WILLaffi-030',
            ],
            [
                'name' => 'DVDを購入 定価 3,498円（税込）',
                'url' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=mfyd231/WILLaffi-030',
                'external_id' => 'WILLaffi-030',
            ],
        ], $movie['metadata']['purchase_links']);
        self::assertSame('※配信日と発売日が異なる場合があります', $movie['metadata']['release_note']);
        self::assertSame('女優 茉白', $movie['metadata']['source_fields']['女優']);
        self::assertSame('発売日 2026年11月17日', $movie['metadata']['source_fields']['発売日']);
        self::assertSame('シリーズ', $movie['metadata']['source_fields']['シリーズ']);
        self::assertSame('レーベル 溜池ゴロー', $movie['metadata']['source_fields']['レーベル']);
        self::assertSame('ジャンル 人妻・主婦 巨乳 デビュー作品 顔射 熟女', $movie['metadata']['source_fields']['ジャンル']);
        self::assertSame('監督 豆沢豆太郎', $movie['metadata']['source_fields']['監督']);
        self::assertSame('品番 DVDMFYD231', $movie['metadata']['source_fields']['品番']);
        self::assertSame('収録時間 DVD120分', $movie['metadata']['source_fields']['収録時間']);
        self::assertSame('価格 DVD3,498円（税込）', $movie['metadata']['source_fields']['価格']);
        self::assertCount(9, $movie['metadata']['source_fields']);
        self::assertCount(9, $movie['screenshots']);
        self::assertStringNotContainsString('MFYD215', (string) json_encode($movie));
    }

    public function test_listing_extracts_every_card_field_without_navigation(): void
    {
        $result = (new Listing($this->clientWithFixture('tameikegoro/list-2026-11-17.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-17'));
        self::assertCount(15, $result->items);
        self::assertSame(self::BASE . '/works/detail/MFYD231', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('MFYD231', $movie['external_id']);
        self::assertSame('神乳Jカップラウンジ嬢人妻・茉白 AV 出演ー。　美貌も体もエロもハイレベルな唯一無二の人妻', $movie['title']);
        self::assertSame('MFYD231', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/11/content/88184/30TlpqhuEmyVvTJy23we5RLP2eEF4ZkYuhEiUUJB.jpg', $movie['cover_url']);
        self::assertNull($movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame([
            [
                'external_id' => '876448',
                'name' => '茉白',
                'name_japanese' => '茉白',
                'url' => 'https://tameikegoro.jp/actress/detail/876448',
                'profile_image_url' => null,
                'birth_date_raw' => null,
                'height_raw' => null,
                'size_raw' => null,
                'aliases' => [],
                'tags' => [],
                'raw_profile' => [],
                'metadata' => [],
            ],
        ], $movie['performers']);
        self::assertSame([], $movie['tags']);
        self::assertSame([], $movie['screenshots']);
        self::assertSame('溜池ゴロー', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年11月17日', $movie['metadata']['search_key']);
        self::assertSame('全15作品中 1 〜 15 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(['MFYD231', 'MFYD215', 'MFYD218', 'MFYD208', 'MFYD221', 'MFYD211', 'MFYD216', 'MFYD212', 'MFYD209', 'MFYD214', 'MFYD213', 'MFYD232', 'MFYD210', 'MFYD217', 'MBYD434'], array_map(static fn($item): string => $item->meta['movie']['code'], $result->items));
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_date_index_follows_manifest_response_and_paginates(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('tameikegoro/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('tameikegoro/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(15, $result->items);
        $meta = $result->items[0]->meta['movie']['metadata'];
        self::assertSame([
            'name' => '2026年11月17日 作品数：15',
            'url' => self::BASE . '/works/list/date/2026-11-17',
            'external_id' => '2026-11-17',
            'date' => '2026-11-17',
            'count' => 15,
        ], $meta['release_group']);
        self::assertSame('2026-11-17', $result->items[0]->meta['movie']['date']);
        self::assertSame('発売日検索結果：2026年11月17日', $meta['search_key']);
        self::assertSame('全15作品中 1 〜 15 タイトルを表示', $meta['listing_range']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(92, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_actress_extracts_every_profile_field_and_work(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('tameikegoro/actress-876448.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/876448'));
        self::assertSame(self::BASE . '/actress/detail/876448', $result->url);
        $person = $result->meta['performer'];
        self::assertSame('876448', $person['external_id']);
        self::assertSame('MASHIRO', $person['name']);
        self::assertSame('茉白', $person['name_japanese']);
        self::assertSame('https://tameikegoro.jp/actress/detail/876448', $person['url']);
        self::assertSame('https://cdn.up-timely.com/image/11/actress_main/876448/0DtZNJd8oKNFUeiDOGqlR35qHDpDV598YXzDKYJG.jpg', $person['profile_image_url']);
        self::assertNull($person['birth_date_raw']);
        self::assertNull($person['height_raw']);
        self::assertSame('B105cm (J) W--cm H--cm', $person['size_raw']);
        self::assertSame(['茉白', 'MASHIRO'], $person['aliases']);
        self::assertSame([], $person['tags']);
        self::assertSame([
            '3サイズ' => 'B105cm (J) W--cm H--cm',
        ], $person['raw_profile']);
        self::assertSame(['https://cdn.up-timely.com/image/11/actress_main/876448/0DtZNJd8oKNFUeiDOGqlR35qHDpDV598YXzDKYJG.jpg'], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame([
            [
                'name' => '神乳Jカップラウンジ嬢人妻・茉白 AV 出演ー。　美貌も体もエロもハイレベルな唯一無二の人妻',
                'url' => 'https://tameikegoro.jp/works/detail/MFYD231',
                'external_id' => 'MFYD231',
                'cover_url' => 'https://cdn.up-timely.com/image/11/content/88184/30TlpqhuEmyVvTJy23we5RLP2eEF4ZkYuhEiUUJB.jpg',
            ],
        ], $person['metadata']['works']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('tameikegoro');
        self::assertInstanceOf(TameikegoroCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-11-17' => CrawlType::Listing,
            '/works/list/genre/315' => CrawlType::Listing,
            '/works/list/label/5531' => CrawlType::Listing,
            '/works/list/series/4525' => CrawlType::Listing,
            '/works/detail/MFYD231' => CrawlType::Detail,
            '/actress/detail/876448' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('tameikegoro', $crawler->name());
    }

}
