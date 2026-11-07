<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\Adapters;

use JOOservices\CrawlerX\Adapters\Commons\Site;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class CommonsFieldsTest extends CrawlerXTestCase
{
    private const ROOT_PAGES = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&pageids=121848656%7C201394750%7C85911169%7C185188568%7C34599022&prop=imageinfo%7Ccategoryinfo%7Cpageprops%7Cpageimages%7Cinfo%7Ccategories&iiprop=timestamp%7Cuser%7Cuserid%7Ccomment%7Cparsedcomment%7Ccanonicaltitle%7Curl%7Csize%7Cdimensions%7Csha1%7Cmime%7Cmediatype%7Cbitdepth%7Cextmetadata&iiurlwidth=320&iiextmetadatalanguage=en&piprop=original%7Cthumbnail%7Cname&pithumbsize=320&inprop=url&cllimit=max';

    private const OTO_PAGES = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&pageids=101986220%7C84315014%7C85911091&prop=imageinfo%7Ccategoryinfo%7Cpageprops%7Cpageimages%7Cinfo%7Ccategories&iiprop=timestamp%7Cuser%7Cuserid%7Ccomment%7Cparsedcomment%7Ccanonicaltitle%7Curl%7Csize%7Cdimensions%7Csha1%7Cmime%7Cmediatype%7Cbitdepth%7Cextmetadata&iiurlwidth=320&iiextmetadatalanguage=en&piprop=original%7Cthumbnail%7Cname&pithumbsize=320&inprop=url&cllimit=max';

    protected function setUp(): void
    {
        parent::setUp();
        CrawlerXFactory::reset();
    }

    public function test_root_listing_yields_one_person_per_idol_category_and_paginates(): void
    {
        $result = $this->listing(Site::membersUrl(Site::ROOT_CATEGORY, 'subcat|file', null, 5), 'listing-root', self::ROOT_PAGES, 'listing-root-pages');

        self::assertSame('category_member', $result->entityType);
        self::assertCount(5, $result->items);
        self::assertSame('performer', $result->items[0]->entityType);
        self::assertSame(
            ['Videos of Gravure idols', 'Gravure idols from Japan by year', 'Oto Abe', 'Jurii Aida', 'Yuzuki Aikawa'],
            array_map(static fn(CrawlItemResultDto $item): string => $item->meta['performer']['name'], $result->items),
        );

        $oto = $result->items[2];
        self::assertSame('https://commons.wikimedia.org/wiki/Category:Oto_Abe', $oto->url);
        self::assertSame('85911169', $oto->meta['performer']['external_id']);
        self::assertSame('https://commons.wikimedia.org/wiki/Category:Oto_Abe', $oto->meta['performer']['url']);
        self::assertSame('https://upload.wikimedia.org/wikipedia/commons/0/0f/Oto_Abe_2021.jpg', $oto->meta['performer']['profile_image_url']);
        self::assertSame('Abe, Oto', $oto->meta['performer']['metadata']['sortkey']);
        self::assertSame('2025-06-19T01:36:48Z', $oto->meta['performer']['metadata']['member_added_at']);
        self::assertSame(3, $oto->meta['performer']['metadata']['category_info']['files']);
        self::assertSame('Q83912169', $oto->meta['performer']['metadata']['wikidata_id']);
        self::assertContains('Gravure idols from Japan', $oto->meta['performer']['tags']);
        self::assertSame('Q83912169', $oto->meta['performer']['raw_profile']['Wikidata']);
        self::assertNull($result->items[0]->meta['performer']['profile_image_url']);

        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertStringContainsString('cmcontinue=subcat%7C414b4142414e452c204d4f4d4f0a4d4f4d4f20414b4142414e45%7C133023225', (string) $result->pagination->nextUrl);
        self::assertStringContainsString('cmtitle=Category%3AGravure+idols+from+Japan', (string) $result->pagination->nextUrl);
    }

    public function test_a_category_listing_turns_files_into_one_photo_galleries_and_continues(): void
    {
        $result = $this->listing(Site::membersUrl('Category:Oto Abe', 'subcat|file', null, 2), 'listing-oto-abe', Site::pagesUrl('101986220|84315014'), 'listing-oto-abe-pages');

        self::assertCount(2, $result->items);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertStringContainsString('cmcontinue=file%7Ce58a87', (string) $result->pagination->nextUrl);

        $first = $result->items[0];
        self::assertSame('gallery', $first->entityType);
        self::assertSame('https://commons.wikimedia.org/wiki/File:Oto_Abe_2021.jpg', $first->url);
        self::assertSame('101986220', $first->meta['gallery']['external_id']);
        self::assertSame('Oto Abe 2021.jpg', $first->meta['gallery']['title']);
        self::assertSame(1, $first->meta['gallery']['photo_count']);
        self::assertSame('https://upload.wikimedia.org/wikipedia/commons/0/0f/Oto_Abe_2021.jpg', $first->meta['gallery']['photos'][0]['image_url']);
        self::assertSame('https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0f/Oto_Abe_2021.jpg/330px-Oto_Abe_2021.jpg', $first->meta['gallery']['photos'][0]['thumbnail_url']);
        self::assertSame('video/webm', $result->items[1]->meta['gallery']['photos'][0]['metadata']['mime']);
    }

    public function test_category_gallery_returns_every_photo_in_order_with_original_and_thumbnail(): void
    {
        $gallery = $this->gallery(Site::categoryUrl('Category:Oto Abe', 'file', null, 50), 'gallery-oto-abe');

        self::assertSame('https://commons.wikimedia.org/wiki/Category:Oto_Abe', $gallery['url']);
        $data = $gallery['entity']['gallery'];
        self::assertSame('85911169', $data['external_id']);
        self::assertSame('Oto Abe', $data['title']);
        self::assertSame(3, $data['photo_count']);
        self::assertSame(['Oto Abe'], $data['performers']);
        self::assertContains('Actresses from Osaka prefecture', $data['categories']);
        self::assertCount(3, $data['photos']);
        self::assertSame(['101986220', '84315014', '85911091'], array_column($data['photos'], 'id'));
        self::assertSame([1, 2, 3], array_column($data['photos'], 'position'));

        // Image slots: page lists 3 files, 3 originals and 3 thumbnails are extracted (originals, never the 330px thumbs).
        $images = array_column($data['photos'], 'image_url');
        self::assertSame([
            'https://upload.wikimedia.org/wikipedia/commons/0/0f/Oto_Abe_2021.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/1/1a/%E5%8A%87%E5%9B%A34%E3%83%89%E3%83%AB50%E3%82%BB%E3%83%B3%E3%83%88%E3%83%BB%E5%AE%89%E5%80%8D%E4%B9%99%E3%81%A1%E3%82%83%E3%82%93%E3%81%8C%E3%83%A4%E3%83%B3%E3%83%9E%E3%82%AC%E5%88%9D%E7%99%BB%E5%A0%B4%21%21%E3%82%B0%E3%82%A2%E3%83%A0%E3%81%A7%E5%BC%BE%E3%81%91%E3%82%8B19%E6%AD%B3%E3%81%AE%E7%AC%91%E9%A1%94%E2%99%A1%E3%80%90%E3%83%A4%E3%83%B3%E3%82%B0%E3%83%9E%E3%82%AC%E3%82%B8%E3%83%B334%E5%8F%B7%E3%80%91.webm',
            'https://upload.wikimedia.org/wikipedia/commons/5/5d/%E5%8A%87%E5%9B%A34%E3%83%89%E3%83%AB50%E3%82%BB%E3%83%B3%E3%83%88%E3%83%BB%E5%AE%89%E5%80%8D%E4%B9%99%EF%BC%88%E8%AC%9B%E8%AB%87%E7%A4%BE%E3%83%A4%E3%83%B3%E3%83%9E%E3%82%ACch%EF%BC%89.jpg',
        ], $images);
        self::assertSame($images, array_values(array_unique($images)));
        self::assertSame(
            'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0f/Oto_Abe_2021.jpg/330px-Oto_Abe_2021.jpg',
            $data['photos'][0]['thumbnail_url'],
        );
        self::assertStringEndsWith('.webm.jpg', $data['photos'][1]['thumbnail_url']);
        self::assertStringContainsString('/330px-', $data['photos'][2]['thumbnail_url']);
        self::assertStringNotContainsString('utm_', implode('', array_column($data['photos'], 'thumbnail_url')) . implode('', $images));
        self::assertSame('https://commons.wikimedia.org/wiki/File:Oto_Abe_2021.jpg', $data['photos'][0]['url']);

        $meta = $data['metadata'];
        self::assertSame(3, $meta['files_on_page']);
        self::assertArrayNotHasKey('next_url', $meta);
        self::assertSame('Q83912169', $meta['performer']['metadata']['wikidata_id']);
        self::assertSame('https://www.wikidata.org/wiki/Q83912169', $meta['performer']['metadata']['wikidata_url']);
        self::assertSame('安倍乙', $meta['performer']['name_japanese']);
        self::assertSame(['安倍乙'], $meta['performer']['aliases']);
        self::assertSame('https://upload.wikimedia.org/wikipedia/commons/0/0f/Oto_Abe_2021.jpg', $meta['performer']['profile_image_url']);
        self::assertSame(['ja' => '安倍乙'], $meta['performer']['metadata']['descriptions']);
        self::assertSame('https://ja.wikipedia.org/wiki/%E5%AE%89%E5%80%8D%E4%B9%99', $meta['performer']['metadata']['wikipedia_links']['ja']);
        self::assertSame(['https://www.imdb.com/nm12438442'], $meta['performer']['metadata']['external_links']);
    }

    public function test_each_photo_carries_license_author_dimensions_and_the_raw_labelled_rows(): void
    {
        $photos = $this->gallery(Site::categoryUrl('Category:Oto Abe', 'file', null, 50), 'gallery-oto-abe')['entity']['gallery']['photos'];

        $still = $photos[0]['metadata'];
        self::assertSame('File:Oto Abe 2021.jpg', $still['title']);
        self::assertSame('Oto Abe 2021', $still['name']);
        self::assertSame('日本の女優、安倍乙。ドラマ『麻雀宝湯記』インタビュー映像より抜粋。', $still['description']);
        self::assertSame('麻雀ウォッチ', $still['author']);
        self::assertSame('https://www.youtube.com/channel/UCQDA1x6VCsJHlrDEPKE0RPQ', $still['author_url']);
        self::assertStringEndsWith('YouTube – View/save archived versions on archive.org', $still['credit']);
        self::assertSame('https://www.youtube.com/watch?v=er_7V28RPvY&t=525', $still['credit_url']);
        self::assertSame('CC BY 3.0', $still['license']);
        self::assertSame('https://creativecommons.org/licenses/by/3.0', $still['license_url']);
        self::assertSame('cc-by-3.0', $still['license_code']);
        self::assertSame('Creative Commons Attribution 3.0', $still['usage_terms']);
        self::assertTrue($still['attribution_required']);
        self::assertTrue($still['copyrighted']);
        self::assertSame('2021-02-02', $still['date']);
        self::assertSame('2021-03-21', $still['date_modified']);
        self::assertSame('2021-03-21T04:59:21Z', $still['uploaded_at']);
        self::assertSame('Stck w', $still['uploader']);
        self::assertSame('https://commons.wikimedia.org/wiki/User:Stck_w', $still['uploader_url']);
        self::assertSame(658053, $still['uploader_id']);
        self::assertStringEndsWith('with UploadWizard', $still['upload_comment']);
        self::assertSame(437, $still['width']);
        self::assertSame(478, $still['height']);
        self::assertSame(25877, $still['size']);
        self::assertSame('image/jpeg', $still['mime']);
        self::assertSame('BITMAP', $still['media_type']);
        self::assertSame(8, $still['bit_depth']);
        self::assertSame('55a8f60fcc8261ff020b5271f6ad067c04659663', $still['sha1']);
        self::assertSame(320, $still['thumbnail_width']);
        self::assertSame(350, $still['thumbnail_height']);
        self::assertSame('https://commons.wikimedia.org/w/index.php?curid=101986220', $still['short_url']);
        self::assertSame(['Media from YouTube', 'Oto Abe', 'Files from external sources with reviewed licenses'], $still['tags']);
        self::assertSame(['CC-BY-3.0', 'Files from external sources with reviewed licenses', 'Media from YouTube', 'Oto Abe'], $still['categories']);
        self::assertSame('2021-03-21 04:59:21', $still['source_fields']['DateTime']);
        self::assertSame('Media from YouTube|Oto Abe|Files from external sources with reviewed licenses', $still['source_fields']['Categories']);
        self::assertSame('cc-by-3.0', $still['source_fields']['License']);
        self::assertSame('True', $still['source_fields']['Copyrighted']);

        $video = $photos[1]['metadata'];
        self::assertSame('video/webm', $video['mime']);
        self::assertSame('VIDEO', $video['media_type']);
        self::assertSame(235.034, $video['duration_seconds']);
        self::assertSame(3.92, $video['duration_minutes']);
        self::assertSame(1920, $video['width']);
        self::assertSame(1080, $video['height']);
        self::assertSame(211110401, $video['size']);
        self::assertSame('2019-07-19', $video['date']);
        self::assertSame('講談社ヤンマガch', $video['author']);
        self::assertContains('Women wearing bikinis', $video['tags']);
        self::assertSame('Imported media from uploads:4d70a9f2-0eab-11ea-8664-0242c0a89504', $video['upload_comment']);

        $screenshot = $photos[2]['metadata'];
        self::assertSame('CC BY-SA 4.0', $screenshot['license']);
        self::assertSame('Creative Commons Attribution-Share Alike 4.0', $screenshot['usage_terms']);
        self::assertSame('This image has been extracted from another file', $screenshot['credit']);
        self::assertSame('2020-01-15', $screenshot['date']);
        self::assertSame('Bject', $screenshot['uploader']);
        self::assertSame(178840, $screenshot['size']);
        self::assertArrayNotHasKey('credit_url', $screenshot);
    }

    public function test_file_page_is_a_one_photo_gallery_with_the_full_file_metadata(): void
    {
        $gallery = $this->gallery(Site::fileUrl('File:Oto Abe 2021.jpg'), 'gallery-file-oto-abe-2021');

        self::assertSame('https://commons.wikimedia.org/wiki/File:Oto_Abe_2021.jpg', $gallery['url']);
        $data = $gallery['entity']['gallery'];
        self::assertSame('101986220', $data['external_id']);
        self::assertSame('Oto Abe 2021.jpg', $data['title']);
        self::assertSame(1, $data['photo_count']);
        self::assertSame('Stck w', $data['uploader']);
        self::assertSame('https://commons.wikimedia.org/wiki/User:Stck_w', $data['uploader_url']);
        self::assertSame('2021-02-02', $data['date']);
        self::assertSame(['CC-BY-3.0', 'Files from external sources with reviewed licenses', 'Media from YouTube', 'Oto Abe'], $data['categories']);
        self::assertSame(['Media from YouTube', 'Oto Abe', 'Files from external sources with reviewed licenses'], $data['tags']);
        self::assertCount(1, $data['photos']);
        self::assertSame('https://upload.wikimedia.org/wikipedia/commons/0/0f/Oto_Abe_2021.jpg', $data['photos'][0]['image_url']);
        self::assertSame('https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0f/Oto_Abe_2021.jpg/330px-Oto_Abe_2021.jpg', $data['photos'][0]['thumbnail_url']);
        self::assertSame(1, $data['photos'][0]['position']);

        $meta = $data['metadata'];
        self::assertSame('https://commons.wikimedia.org/wiki/File:Oto_Abe_2021.jpg', $meta['source_url']);
        self::assertSame('Oto Abe 2021', $meta['name']);
        self::assertSame('日本の女優、安倍乙。ドラマ『麻雀宝湯記』インタビュー映像より抜粋。', $meta['description']);
        self::assertSame('麻雀ウォッチ', $meta['author']);
        self::assertSame('https://www.youtube.com/channel/UCQDA1x6VCsJHlrDEPKE0RPQ', $meta['author_url']);
        self::assertStringStartsWith('CC BY ライセンスで公開された動画', $meta['credit']);
        self::assertSame('CC BY 3.0', $meta['license']);
        self::assertSame('https://creativecommons.org/licenses/by/3.0', $meta['license_url']);
        self::assertSame('2021-03-21T04:59:21Z', $meta['uploaded_at']);
    }

    public function test_person_page_returns_the_profile_image_names_links_and_category_data(): void
    {
        $result = $this->crawlFixture(Site::categoryUrl('Category:Yuzuki Aikawa'), 'performer-yuzuki-aikawa');

        self::assertSame('performer', $result->entityType);
        self::assertSame('https://commons.wikimedia.org/wiki/Category:Yuzuki_Aikawa', $result->url);
        $person = $result->meta['performer'];
        self::assertSame('34599022', $person['external_id']);
        self::assertSame('Yuzuki Aikawa', $person['name']);
        self::assertSame('愛川ゆず季', $person['name_japanese']);
        self::assertSame('https://commons.wikimedia.org/wiki/Category:Yuzuki_Aikawa', $person['url']);
        self::assertSame(
            'https://upload.wikimedia.org/wikipedia/commons/a/a9/4.29%E4%B8%A1%E5%9B%BD%E3%81%B8%E5%90%91%E3%81%91_%E6%84%9B%E5%B7%9D%E3%82%86%E3%81%9A%E5%AD%A3%E3%83%A9%E3%82%B9%E3%83%88%E3%83%A1%E3%83%83%E3%82%BB%E3%83%BC%E3%82%B8.jpg',
            $person['profile_image_url'],
        );
        self::assertSame(['يوزوكي إيكاوا', 'ياوزوكى ايكاوا', 'یوزوکی آیکاوا', '愛川ゆず季', '愛川柚季'], $person['aliases']);
        self::assertSame(
            ['1983 births', 'Aikawa (surname)', 'Births in Niihama, Ehime', 'Female professional wrestlers from Japan', 'Gravure idols from Japan', 'Living people', 'People by name', 'Uses of Wikidata Infobox', 'Uses of Wikidata Infobox with no surname sitelink', 'Women by name', 'Women of Japan by name', 'Yuzuki (given name)'],
            $person['tags'],
        );
        self::assertSame(
            ['Wikidata' => 'Q8062262', 'Default sort' => 'Aikawa, Yuzuki', 'Files' => '5', 'Subcategories' => '0', 'Pages' => '0'],
            $person['raw_profile'],
        );

        $meta = $person['metadata'];
        self::assertSame('Q8062262', $meta['wikidata_id']);
        self::assertSame('https://www.wikidata.org/wiki/Q8062262', $meta['wikidata_url']);
        self::assertSame(['ja' => 'https://ja.wikipedia.org/wiki/%E6%84%9B%E5%B7%9D%E3%82%86%E3%81%9A%E5%AD%A3'], $meta['wikipedia_links']);
        self::assertSame('Yuzuki Aikawa (愛川 ゆず季 Aikawa Yuzuki, born May 16, 1983) is a Japanese gravure idol and retired professional wrestler.', $meta['descriptions']['en']);
        self::assertSame('愛川ゆず季', $meta['descriptions']['ja']);
        self::assertSame('愛川柚季', $meta['language_titles']['zh']);
        self::assertSame('Aikawa, Yuzuki', $meta['sortkey']);
        self::assertSame(['size' => 5, 'pages' => 0, 'files' => 5, 'subcats' => 0, 'hidden' => false], $meta['category_info']);
        self::assertSame('4.29両国へ向け_愛川ゆず季ラストメッセージ.jpg', $meta['profile_image_file']);
        self::assertSame(337, $meta['profile_image_width']);
        self::assertSame(422, $meta['profile_image_height']);
        self::assertStringEndsWith('/330px-4.29%E4%B8%A1%E5%9B%BD%E3%81%B8%E5%90%91%E3%81%91_%E6%84%9B%E5%B7%9D%E3%82%86%E3%81%9A%E5%AD%A3%E3%83%A9%E3%82%B9%E3%83%88%E3%83%A1%E3%83%83%E3%82%BB%E3%83%BC%E3%82%B8.jpg', $meta['profile_thumbnail_url']);
        self::assertSame(320, $meta['profile_thumbnail_width']);
        self::assertSame(401, $meta['profile_thumbnail_height']);
        self::assertSame([
            'http://yuzupon-ring.p-kit.com/',
            'https://data.europeana.eu/agent/base/46453',
            'https://www.imdb.com/nm3843258',
            'https://musicbrainz.org/artist/1029b289-2eb8-4732-b139-610fc79f0796',
            'https://id.ndl.go.jp/auth/ndlna/01212146',
            'https://viaf.org/en/viaf/252798034',
        ], $meta['external_links']);
        self::assertSame('2026-11-02T09:23:45Z', $meta['touched']);
        self::assertSame(819027428, $meta['last_revision_id']);
        self::assertSame('2023-11-06T15:01:51Z', $meta['last_edited_at']);
    }

    public function test_urls_infer_the_capability_without_overrides(): void
    {
        foreach ([
            [Site::membersUrl(Site::ROOT_CATEGORY, 'subcat|file', null, 5), 'listing-root', self::ROOT_PAGES, 'listing-root-pages', CrawlListResultDto::class],
            [Site::categoryUrl('Category:Oto Abe', 'file', null, 50), 'gallery-oto-abe', self::OTO_PAGES, 'gallery-oto-abe-pages', CrawlItemResultDto::class],
            [Site::fileUrl('File:Oto Abe 2021.jpg'), 'gallery-file-oto-abe-2021', null, null, CrawlItemResultDto::class],
            [Site::categoryUrl('Category:Yuzuki Aikawa'), 'performer-yuzuki-aikawa', null, null, CrawlItemResultDto::class],
        ] as [$url, $fixture, $pagesUrl, $pagesFixture, $class]) {
            FixtureResponder::for('GET', $url)->file('commons/' . $fixture . '.json');
            if ($pagesUrl !== null) {
                FixtureResponder::for('GET', $pagesUrl)->file('commons/' . $pagesFixture . '.json');
            }

            self::assertInstanceOf($class, CrawlerX::url($url)->crawl());
        }
    }

    private function listing(string $url, string $fixture, string $pagesUrl, string $pagesFixture): CrawlListResultDto
    {
        FixtureResponder::for('GET', $url)->file('commons/' . $fixture . '.json');
        FixtureResponder::for('GET', $pagesUrl)->file('commons/' . $pagesFixture . '.json');
        $result = CrawlerX::url($url)->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $result);

        return $result;
    }

    private function crawlFixture(string $url, string $fixture): CrawlItemResultDto
    {
        FixtureResponder::for('GET', $url)->file('commons/' . $fixture . '.json');
        $result = CrawlerX::url($url)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $result);

        return $result;
    }

    /**
     * @return array{url: string, entity: array<string, mixed>}
     */
    private function gallery(string $url, string $fixture): array
    {
        FixtureResponder::for('GET', $url)->file('commons/' . $fixture . '.json');
        FixtureResponder::for('GET', self::OTO_PAGES)->file('commons/gallery-oto-abe-pages.json');
        $result = CrawlerX::url($url)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('gallery', $result->entityType);

        return ['url' => $result->url, 'entity' => $result->meta];
    }
}
