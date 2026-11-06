<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Kirakira;

use JOOservices\CrawlerX\Adapters\Kirakira\KirakiraCrawler;
use JOOservices\CrawlerX\Adapters\Kirakira\Site;
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

final class KirakiraTest extends TestCase
{
    private const BASE = 'https://kirakira-av.com';

    private const CDN = 'https://cdn.up-timely.com/image/3/content/';

    public function test_new_release_detail_fields_from_real_capture(): void
    {
        $result = $this->detail('KIBD359');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/KIBD359', $result->url);
        self::assertSame('KIBD359', $movie['external_id']);
        self::assertSame('KIBD359', $movie['code']);
        self::assertSame('「もうホテルで良くない？」即マン型絶倫ギャルとアプリで待ち合わせ>>>即ホテル即ハメしちゃいましたBEST', $movie['title']);
        self::assertStringStartsWith('【もうホテルで良くない？】マッチングアプリで出会ったヤリマンGAL', $movie['description']);
        self::assertStringEndsWith('大当たりのヤリマンギャルと昼からヤリまくり！', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(170, $movie['duration']);
        self::assertSame(self::CDN . '88143/kwfNGT6XhFkWPjwvUPTlhKHA2UPkRafdIJNwmDye.jpg', $movie['cover_url']);
        self::assertCount(21, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . '88143/FJMlIFqBrEeOqoeOK7VszqDP2mDevcW8S92Net5o.jpg', $movie['screenshots'][20]['url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['中出し', '騎乗位', '痴女', '巨尻', 'ギャル'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('kira*kira', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([['name' => 'マッチングアプリで待ち合わせたギャルは即マン型の都合のいい絶倫ヤリマンだった！', 'url' => self::BASE . '/works/list/series/5538', 'external_id' => '5538']], $meta['series']);
        self::assertSame([['name' => 'kira☆kira', 'url' => self::BASE . '/works/list/label/6770', 'external_id' => '6770']], $meta['label']);
        self::assertSame([['name' => '2026年11月17日', 'url' => self::BASE . '/works/list/date/2026-11-17', 'external_id' => '2026-11-17']], $meta['release_links']);
        self::assertCount(5, $meta['genres']);
        self::assertSame('128', $meta['genres'][0]['external_id']);
        self::assertSame('中出し', $meta['genres'][0]['name']);
        self::assertSame(self::BASE . '/works/list/genre/310', $meta['genres'][4]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'KIBD359', 'duration' => 170, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/Ej9wLR4Ukx9XMpUdhf-08SFhAx9USRSPd4XWUXlOURMVDmEvy_H257wJeboZys/kibd00359dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . '88143/sample_video_image_file/BO4K8FhoGqom9Mszh15ERGdsK3jaJDgDWAyB964b.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=kibd00359/WILLaffi-036', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 3,498円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame('女優', $meta['source_fields']['女優']);
        self::assertSame('発売日 2026年11月17日', $meta['source_fields']['発売日']);
        self::assertSame('レーベル kira☆kira', $meta['source_fields']['レーベル']);
        self::assertSame('ジャンル 中出し 騎乗位 痴女 巨尻 ギャル', $meta['source_fields']['ジャンル']);
        self::assertSame('監督 ---', $meta['source_fields']['監督']);
        self::assertSame('品番 DVDKIBD359', $meta['source_fields']['品番']);
        self::assertSame('収録時間 DVD170分', $meta['source_fields']['収録時間']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        self::assertStringStartsWith('シリーズ マッチングアプリ', $meta['source_fields']['シリーズ']);
        self::assertCount(9, $meta['source_fields']);
        self::assertStringNotContainsString('KIBD360', (string) json_encode($movie));
    }

    public function test_catalogue_detail_with_performers_and_no_sample_video(): void
    {
        $movie = $this->detail('KIBD249')->meta['movie'];
        self::assertSame('KIBD249', $movie['code']);
        self::assertSame('ギャル大好きユーザーの皆さまで決める!最強にヌケる平成ギャル総選挙BEST30', $movie['title']);
        self::assertStringStartsWith('ギャル好きユーザーの皆さまで決める！最強にヌケる！', $movie['description']);
        self::assertSame('2019-12-19', $movie['date']);
        self::assertSame(480, $movie['duration']);
        self::assertSame(self::CDN . '12261/KIBD249_2.jpg', $movie['cover_url']);
        self::assertCount(1, $movie['screenshots']);
        self::assertCount(21, $movie['performers']);
        self::assertSame('38526', $movie['performers'][0]['external_id']);
        self::assertSame('AIKA', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/actress/detail/38526', $movie['performers'][0]['url']);
        self::assertSame('42291', $movie['performers'][20]['external_id']);
        self::assertSame('北乃ちか', $movie['performers'][20]['name']);
        self::assertSame(['日焼け', 'ギャル', '巨乳', '痴女', 'フェラ'], $movie['tags']);
        self::assertSame('809', $movie['metadata']['series'][0]['external_id']);
        self::assertSame('kira★kiraBEST', $movie['metadata']['series'][0]['name']);
        self::assertSame('6770', $movie['metadata']['label'][0]['external_id']);
        self::assertSame('3,278円（税込）', $movie['metadata']['formats'][0]['price']);
        self::assertSame(480, $movie['metadata']['formats'][0]['duration']);
        self::assertSame('2019-12-19', $movie['metadata']['release_links'][0]['external_id']);
        self::assertNull($movie['metadata']['director']);
        self::assertNull($movie['metadata']['sample_video_url']);
        self::assertNull($movie['metadata']['sample_video_poster_url']);
        self::assertStringStartsWith('女優 AIKA RUMIKA', $movie['metadata']['source_fields']['女優']);
        self::assertStringEndsWith('北乃ちか', $movie['metadata']['source_fields']['女優']);
        self::assertSame('収録時間 DVD480分', $movie['metadata']['source_fields']['収録時間']);
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('kirakira/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('kirakira/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(2, $result->items);
        self::assertSame(self::BASE . '/works/detail/KIBD359', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('KIBD359', $movie['external_id']);
        self::assertSame('KIBD359', $movie['code']);
        self::assertSame('「もうホテルで良くない？」即マン型絶倫ギャルとアプリで待ち合わせ>>>即ホテル即ハメしちゃいましたBEST', $movie['title']);
        self::assertSame(self::CDN . '88143/fvV4Qz0cik6H4aIAvCj45eQVxJzNllIa52DMxB74.jpg', $movie['cover_url']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame(2, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-17', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-17', $movie['metadata']['release_group']['url']);
        self::assertSame('全2作品中 1 〜 2 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年11月17日', $movie['metadata']['search_key']);
        self::assertSame('KIBD360', $result->items[1]->meta['movie']['external_id']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_genre_listing_paginates_and_strips_tracking_query(): void
    {
        $url = self::BASE . '/works/list/genre/128';
        $result = (new Listing($this->clientWithFixture('kirakira/list-genre-128.html'), $this->config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(30, $result->items);
        self::assertSame(self::BASE . '/works/detail/KIBD359', $result->items[0]->url);
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('KIBD360', $movie['external_id']);
        self::assertNull($movie['date']);
        self::assertSame('中出し', $movie['metadata']['search_key']);
        self::assertSame('全633作品中 1 〜 30 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame($url . '?page=2', $result->pagination->nextUrl);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('kirakira/actress-33482.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/33482'));
        $person = $result->meta['performer'];
        self::assertSame('33482', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/33482', $person['url']);
        self::assertSame('AINA RINA', $person['name']);
        self::assertSame('愛菜りな', $person['name_japanese']);
        self::assertSame(['愛菜りな', 'AINA RINA'], $person['aliases']);
        self::assertSame('1986-08-29', $person['birth_date_raw']);
        self::assertSame('153cm', $person['height_raw']);
        self::assertSame('B88cm (E) W58cm H86cm', $person['size_raw']);
        self::assertSame('千葉県', $person['raw_profile']['出身地']);
        self::assertSame('テニス', $person['raw_profile']['趣味']);
        self::assertSame('1986年8月29日', $person['raw_profile']['誕生日']);
        self::assertCount(5, $person['raw_profile']);
        self::assertSame('https://cdn.up-timely.com/image/3/actress_main/33482/MK0J3tu2Ip3uGXrVu60nbEsDBt6vWuP8vJPfY7c3.jpeg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertCount(12, $person['metadata']['works']);
        self::assertSame('KIBD249', $person['metadata']['works'][0]['external_id']);
        self::assertSame(self::BASE . '/works/detail/KIBD249', $person['metadata']['works'][0]['url']);
        self::assertSame('ギャル大好きユーザーの皆さまで決める!最強にヌケる平成ギャル総選挙BEST30', $person['metadata']['works'][0]['name']);
        self::assertSame(self::CDN . '12261/KIBD249_1.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame('KISD009', $person['metadata']['works'][11]['external_id']);
        self::assertSame('全12作品中 1 〜 12 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('kirakira');
        self::assertInstanceOf(KirakiraCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-11-17' => CrawlType::Listing,
            '/works/list/genre/128' => CrawlType::Listing,
            '/works/list/label/6770' => CrawlType::Listing,
            '/works/list/series/5538' => CrawlType::Listing,
            '/works/detail/KIBD359' => CrawlType::Detail,
            '/actress/detail/33482' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('kirakira', $crawler->name());
    }

    public function test_shared_base_leaves_s1_output_unchanged(): void
    {
        $result = (new Detail($this->clientWithFixture('s1/detail-SNOS524.html'), S1Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/detail/SNOS524'));
        self::assertArrayNotHasKey('label', $result->meta['movie']['metadata']);
        self::assertSame('イナバール', $result->meta['movie']['metadata']['director']);
    }

    private function detail(string $code): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('kirakira/detail-' . $code . '.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }

    private function config(): SiteConfig
    {
        return Site::config();
    }
}
