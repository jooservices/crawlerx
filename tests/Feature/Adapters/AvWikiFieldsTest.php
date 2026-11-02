<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\Adapters;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\RecordedRequest;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;

final class AvWikiFieldsTest extends CrawlerXTestCase
{
    private const LIST_URL = 'https://av-wiki.net/wp-json/wp/v2/posts?per_page=2&page=1&_embed=1';

    private const DETAIL_URL = 'https://av-wiki.net/200gana-3482/';

    private const DETAIL_API_URL = 'https://av-wiki.net/wp-json/wp/v2/posts?slug=200gana-3482&_embed=1';

    public function test_rest_lookups_use_the_configured_crawler_user_agent(): void
    {
        $previousUserAgent = getenv('CRAWLERX_USER_AGENT');
        putenv('CRAWLERX_USER_AGENT=AVWikiFixtureTest/1.0');
        CrawlerXFactory::reset();

        try {
            $this->listingFixtures();
            CrawlerX::url(self::LIST_URL)->crawl();

            $matchingRequests = array_filter(
                ClientBuilder::recorded(),
                static fn(RecordedRequest $request): bool => $request->request->getUri()->getPath() === '/wp-json/wp/v2/tags'
                    && $request->request->getHeaderLine('User-Agent') === 'AVWikiFixtureTest/1.0',
            );
            self::assertNotSame([], $matchingRequests);
        } finally {
            CrawlerXFactory::reset();
            if (is_string($previousUserAgent)) {
                putenv('CRAWLERX_USER_AGENT=' . $previousUserAgent);
            } else {
                putenv('CRAWLERX_USER_AGENT');
            }
        }
    }

    public function test_listing_preserves_every_post_field_and_extracts_rich_tag_and_category_data(): void
    {
        $this->listingFixtures();
        $result = CrawlerX::url(self::LIST_URL)->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertCount(2, $result->items);
        self::assertSame(95882, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);

        $movie = $result->items[1]->meta['movie'];
        self::assertSame('1691515', $movie['external_id']);
        self::assertSame('SNOS-484', $movie['code']);
        self::assertSame('新入社員の打ち上げ後…酔って同期女子2人が目の前でキス 興奮した僕も一緒にベロキス3Pしまくった思い出 川越にこ 安達夕莉に出てるAV女優名まとめ', $movie['title']);
        self::assertSame('https://av-wiki.net/snos-484/', $result->items[1]->url);
        self::assertSame('2026-11-06', $movie['date']);
        self::assertNull($movie['cover_url']);
        self::assertSame(['安達夕莉', '川越にこ'], $movie['tags']);
        self::assertCount(2, $movie['performers']);

        $metadata = $movie['metadata'];
        self::assertSame('エスワン ナンバーワンスタイル', $metadata['maker']);
        self::assertSame('S1 NO.1 STYLE', $metadata['label']);
        self::assertSame('2026-11-06', $metadata['release_date_raw']);
        self::assertSame('2026-11-06T09:55:14+09:00', $metadata['posted_date']);
        self::assertSame('2026-11-06T00:55:14+00:00', $metadata['posted_date_gmt']);
        self::assertSame('2026-11-06T09:55:14+09:00', $metadata['modified_date']);
        self::assertSame('2026-11-06T00:55:14+00:00', $metadata['modified_date_gmt']);
        self::assertSame('SNOS-484', $metadata['fields']['メーカー品番']);
        self::assertSame('snos00484', $metadata['fanza_code']);
        self::assertSame('安達夕莉', $movie['performers'][0]['name']);
        self::assertSame('あだちゆうり', $movie['performers'][0]['metadata']['name_reading']);
        self::assertSame('yuuri adachi', $movie['performers'][0]['metadata']['name_romanized']);
        self::assertSame('https://pics.dmm.co.jp/mono/actjpgs/adati_yuuri.jpg', $movie['performers'][0]['profile_image_url']);
        self::assertSame('2002-01-24', $movie['performers'][0]['birth_date_raw']);
        self::assertSame('T152', $movie['performers'][0]['height_raw']);
        self::assertSame('T152 / B87(Gカップ) / W56 / H83', $movie['performers'][0]['size_raw']);
        self::assertSame([], $movie['performers'][0]['aliases']);
        self::assertSame('https://x.com/yuuri_adachi', $movie['performers'][0]['metadata']['sns'][0]['url']);
        self::assertSame(39, $movie['performers'][0]['metadata']['wordpress']['count']);
        self::assertSame('2003-04-23', $movie['performers'][1]['birth_date_raw']);
        self::assertSame('T150', $movie['performers'][1]['height_raw']);
        self::assertSame('T150 / B85-W55-H80', $movie['performers'][1]['size_raw']);
        self::assertSame('niko kawagoe', $movie['performers'][1]['metadata']['name_romanized']);
        self::assertSame('https://image-optimizer.osusume.dmm.co.jp/actress/kawagoe_niko.jpg', $movie['performers'][1]['profile_image_url']);
        self::assertSame(49, $movie['performers'][1]['metadata']['wordpress']['count']);
        self::assertSame([
            'https://x.com/kawagoeniko',
            'https://www.instagram.com/kawagoeniko',
        ], array_column($movie['performers'][1]['metadata']['sns'], 'url'));
        self::assertSame([127889], $metadata['category_ids']);
        self::assertSame([121544, 125105], $metadata['tag_ids']);
        $category = $metadata['categories'][0];
        self::assertSame(['id', 'count', 'description', 'link', 'name', 'slug', 'taxonomy', 'parent', 'meta', '_links'], array_keys($category));
        self::assertSame(127889, $category['id']);
        self::assertSame(404, $category['count']);
        self::assertSame('', $category['description']);
        self::assertSame('https://av-wiki.net/fanza-video/s1-no1-style/snos/', $category['link']);
        self::assertSame('エスワン - SNOS', $category['name']);
        self::assertSame('snos', $category['slug']);
        self::assertSame('category', $category['taxonomy']);
        self::assertSame(7526, $category['parent']);
        self::assertSame([], $category['meta']);
        self::assertSame('https://av-wiki.net/wp-json/wp/v2/categories/7526', $category['_links']['up'][0]['href']);
        self::assertSame('AV女優の名前が知りたい！', $metadata['author']['name']);
        self::assertSame('av-wiki', $metadata['author']['slug']);
        self::assertSame($metadata['wordpress']['_embedded']['author'][0], $metadata['author']);
        self::assertSame('安達夕莉（あだちゆうり）- yuuri adachi', $movie['performers'][0]['raw_profile']['AV女優名']);
        self::assertNull($movie['description']);

        $wordpress = $metadata['wordpress'];
        self::assertSame([
            'id', 'date', 'date_gmt', 'guid', 'modified', 'modified_gmt', 'slug', 'status', 'type', 'link',
            'title', 'content', 'excerpt', 'author', 'featured_media', 'comment_status', 'ping_status', 'sticky',
            'template', 'format', 'meta', 'categories', 'tags', 'class_list', '_links', '_embedded',
            'featured_media_id', 'content_protected', 'excerpt_protected',
        ], array_keys($wordpress));
        self::assertSame('1691515', (string) $wordpress['id']);
        self::assertSame('2026-11-06T09:55:14+09:00', $wordpress['date']);
        self::assertSame('2026-11-06T00:55:14+00:00', $wordpress['date_gmt']);
        self::assertSame('https://av-wiki.net/snos-484/', $wordpress['guid']['rendered']);
        self::assertSame('2026-11-06T09:55:14+09:00', $wordpress['modified']);
        self::assertSame('2026-11-06T00:55:14+00:00', $wordpress['modified_gmt']);
        self::assertSame('snos-484', $wordpress['slug']);
        self::assertSame('publish', $wordpress['status']);
        self::assertSame('post', $wordpress['type']);
        self::assertSame('https://av-wiki.net/snos-484/', $wordpress['link']);
        self::assertSame('新入社員の打ち上げ後…酔って同期女子2人が目の前でキス 興奮した僕も一緒にベロキス3Pしまくった思い出 川越にこ 安達夕莉に出てるAV女優名まとめ', $wordpress['title']['rendered']);
        self::assertStringContainsString('SNOS-484', $wordpress['content']['rendered_text']);
        self::assertSame('', $wordpress['excerpt']['rendered_text']);
        self::assertSame(1, $wordpress['author']);
        self::assertSame(0, $wordpress['featured_media']);
        self::assertSame(0, $wordpress['featured_media_id']);
        self::assertSame('open', $wordpress['comment_status']);
        self::assertSame('closed', $wordpress['ping_status']);
        self::assertFalse($wordpress['sticky']);
        self::assertSame('', $wordpress['template']);
        self::assertSame('standard', $wordpress['format']);
        self::assertSame(['footnotes' => ''], $wordpress['meta']);
        self::assertSame([127889], $wordpress['categories']);
        self::assertSame([121544, 125105], $wordpress['tags']);
        self::assertFalse($wordpress['content_protected']);
        self::assertFalse($wordpress['excerpt_protected']);
        self::assertSame('post-1691515', $wordpress['class_list'][0]);
        self::assertSame('https://av-wiki.net/wp-json/wp/v2/posts', $wordpress['_links']['collection'][0]['href']);
        self::assertSame('AV女優の名前が知りたい！', $wordpress['_embedded']['author'][0]['name']);
    }

    public function test_detail_maps_every_visible_product_field_without_related_page_noise(): void
    {
        $this->detailFixtures();
        $result = CrawlerX::url(self::DETAIL_URL)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        $movie = $result->meta['movie'];
        self::assertSame('1691411', $movie['external_id']);
        self::assertSame('200GANA-3482', $movie['code']);
        self::assertSame('小柄でグラマラスなスタイルが最高！揉みに揉みしばいて、恥じらいMAX！ヤリたい女の子部門3位の抱き枕女子 マジ軟派、初撮。 2368に出てるAV女優は誰？ 名前は？', $movie['title']);
        self::assertSame('2026-11-02', $movie['date']);
        self::assertSame('https://image.mgstage.com/images/nanpatv/200gana/3482/pb_p_200gana-3482.jpg', $movie['cover_url']);
        self::assertSame('ナンパTV', $movie['metadata']['maker']);
        self::assertSame('ナンパTV', $movie['metadata']['fields']['メーカー']);
        self::assertSame('倉科みくる', $movie['metadata']['fields']['AV女優名']);
        self::assertSame('200GANA-3482', $movie['metadata']['fields']['メーカー品番']);
        self::assertSame('200GANA-3482', $movie['metadata']['fields']['MGS品番']);
        self::assertSame('みくる 31歳 歯科助手', $movie['metadata']['amateur_name']);
        self::assertSame('マジ軟派、初撮。', $movie['metadata']['series']);
        self::assertSame('2026-11-02', $movie['metadata']['fields']['配信開始日']);
        self::assertSame('2026-11-02', $movie['metadata']['release_date_raw']);
        self::assertSame('小柄でグラマラスなスタイルが最高！揉みに揉みしばいて、恥じらいMAX！ヤリたい女の子部門3位の抱き枕女子 マジ軟派、初撮。 2368に出てるAV女優は誰？ 名前は？ナンパTV - 200GANA-3482', $movie['metadata']['titles']['page_heading']);
        self::assertSame('2026-11-02', $movie['metadata']['page_date']);
        self::assertSame(['倉科みくる'], $movie['tags']);
        self::assertSame('128919', $movie['performers'][0]['external_id']);
        self::assertSame('倉科みくる', $movie['performers'][0]['name']);
        self::assertSame('https://av-wiki.net/av-actress/%e5%80%89%e7%a7%91%e3%81%bf%e3%81%8f%e3%82%8b/', $movie['performers'][0]['url']);
        self::assertSame('2858', (string) $movie['metadata']['categories'][0]['id']);
        self::assertSame(1367, $movie['metadata']['categories'][0]['count']);
        self::assertStringContainsString('AVメーカー〔ナンパTV〕', $movie['metadata']['categories'][0]['description']);
        self::assertStringNotContainsString('<p>', $movie['metadata']['categories'][0]['description']);
        self::assertSame('https://av-wiki.net/mgstage/nanpatv/series-id-2858/', $movie['metadata']['categories'][0]['link']);
        self::assertSame('2840', (string) $movie['metadata']['categories'][0]['parent']);
        self::assertSame('マジ軟派、初撮。', $movie['metadata']['categories'][0]['name']);
        self::assertSame('series-id-2858', $movie['metadata']['categories'][0]['slug']);
        self::assertSame('category', $movie['metadata']['categories'][0]['taxonomy']);
        self::assertSame([], $movie['metadata']['categories'][0]['meta']);
        self::assertSame('https://av-wiki.net/wp-json/wp/v2/categories/2840', $movie['metadata']['categories'][0]['_links']['up'][0]['href']);
        self::assertCount(1, $movie['screenshots']);
        self::assertSame('https://image.mgstage.com/images/nanpatv/200gana/3482/pf_t1_200gana-3482.jpg', $movie['screenshots'][0]['url']);
        self::assertCount(1, $movie['metadata']['links']);
        self::assertSame('https://www.mgstage.com/product/product_detail/200GANA-3482/?aff=QTWJYS6BP24YCHBPG2PDC83284', $movie['metadata']['links'][0]['url']);
        self::assertStringNotContainsString('まとめページ', $movie['metadata']['article_text']);
        self::assertStringNotContainsString('MGS動画', $movie['metadata']['article_text']);
        self::assertNull($movie['description']);
    }

    private function listingFixtures(): void
    {
        $this->respondWithFixture('GET', self::LIST_URL, 'avwiki/listing-page1.json', 200, [
            'X-WP-Total' => '191763',
            'X-WP-TotalPages' => '95882',
        ]);
        $this->respondWithFixture('GET', 'https://av-wiki.net/wp-json/wp/v2/tags?include=121544,125105,128853', 'avwiki/tags-list-page1.json');
        $this->respondWithFixture('GET', 'https://av-wiki.net/wp-json/wp/v2/categories?include=127889', 'avwiki/categories-list-page1.json');
    }

    private function detailFixtures(): void
    {
        $this->respondWithFixture('GET', self::DETAIL_URL, 'avwiki/detail-200gana-3482.html');
        $this->respondWithFixture('GET', self::DETAIL_API_URL, 'avwiki/detail-200gana-3482.json');
        $this->respondWithFixture('GET', 'https://av-wiki.net/wp-json/wp/v2/categories?include=2858', 'avwiki/categories-detail-200gana-3482.json');
    }
}
