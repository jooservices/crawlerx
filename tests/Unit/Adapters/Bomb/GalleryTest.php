<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Bomb;

use JOOservices\CrawlerX\Adapters\Bomb\Types\Gallery;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class GalleryTest extends TestCase
{
    private const BASE = 'https://www.bomb.tv/plus';

    /** @return array<string, mixed> */
    private function gallery(string $fixture, string $idol): array
    {
        $result = (new Gallery($this->clientWithFixture($fixture)))->execute(new CrawlRequestDto(
            url: self::BASE . '/idolindex/' . $idol . '.html',
            type: CrawlType::Gallery,
        ));

        self::assertSame('gallery', $result->entityType);
        self::assertSame(self::BASE . '/idolindex/' . $idol . '.html', $result->url);
        $gallery = $result->meta['gallery'] ?? null;
        self::assertIsArray($gallery);

        return $gallery;
    }

    public function test_idol_page_fields_from_real_capture(): void
    {
        $gallery = $this->gallery('bomb/idol-mita.html', 'mita');

        self::assertSame('mita', $gallery['external_id']);
        self::assertSame('三田悠貴', $gallery['title']);
        self::assertSame(['三田悠貴'], $gallery['performers']);
        self::assertSame(12, $gallery['photo_count']);
        self::assertCount(31, $gallery['tags']);
        self::assertSame(['Gカップ', '軽トラ女子', '誘惑'], array_slice($gallery['tags'], 0, 3));
        self::assertContains('チューブ水着', $gallery['tags']);
        self::assertNotContains('三田悠貴', $gallery['tags'], 'the performer name parts are not tags');
        self::assertNotContains('BOMB.tv', $gallery['tags'], 'the site words are not tags');

        $metadata = $gallery['metadata'];
        self::assertSame('三田悠貴', $metadata['name_japanese']);
        self::assertSame('MITA YUUKI', $metadata['name_romaji']);
        self::assertSame(self::BASE . '/idolindex/images/n_mita.gif', $metadata['name_image_url']);
        self::assertSame('ONE PUBLISHING / ATREE', $metadata['credit']);
        self::assertSame('本サイト内のすべてのコンテンツは、個人での利用に限定されております。転載・配布を禁止いたします。', $metadata['license_notice']);
        self::assertSame(3, $metadata['pack_count']);
        self::assertContains('BOMB.tv+', $metadata['keywords']);
        self::assertContains('三田悠貴', $metadata['keywords']);
    }

    public function test_every_pack_field_from_real_capture(): void
    {
        $packs = $this->gallery('bomb/idol-mita.html', 'mita')['metadata']['packs'];

        self::assertSame(['mita03', 'mita02', 'mita01'], array_column($packs, 'id'));
        self::assertSame([3, 2, 1], array_column($packs, 'number'));
        self::assertSame(['p_mita03', 'p_mita02', 'p_mita01'], array_column($packs, 'product_id'));
        self::assertSame([60, 70, 70], array_column($packs, 'image_count'));
        self::assertSame([3, 1, 1], array_column($packs, 'movie_count'));
        self::assertSame([null, null, null], array_column($packs, 'voice_count'));
        self::assertSame([1000, 1000, 1000], array_column($packs, 'price_jpy'));

        $first = $packs[0];
        self::assertSame('超絶やわらかGカップバスト 艶めかしく誘惑する甘い香り', $first['title']);
        self::assertSame('三田悠貴　超絶やわらかGカップバスト 艶めかしく誘惑する甘い香り', $first['caption']);
        self::assertSame(
            "圧倒的なGカップボディ＆“軽トラ女子”で人気の【三田悠貴】衝撃グラビア!\n色気全開ランジェリーや軟乳はみ出す極小面積ビキニ、日焼け跡と下乳チラ見せバンドゥビキニでボリューム感をたっぷり実感できるグラビアに！",
            $first['description'],
        );
        self::assertSame('http://www.bomb.tv/plus_src/select_payment.php?iid=p_mita03&cid=plus', $first['purchase_url']);
        self::assertSame(self::BASE . '/sample/mita03.html', $first['sample_page_url']);
        self::assertSame(self::BASE . '/idolindex/images/mita03.jpg', $first['cover_url']);
        self::assertSame(
            [self::BASE . '/idolindex/images/mita03_s1.jpg', self::BASE . '/idolindex/images/mita03_s2.jpg'],
            $first['preview_urls'],
        );
        self::assertSame(self::BASE . '/sample/images/mita03.jpg', $first['sample_image_url']);
        self::assertSame(['contents' => '画像60点 mp4ムービー3本'], $first['source_fields']);
    }

    public function test_every_image_slot_is_extracted_in_page_order(): void
    {
        $photos = $this->gallery('bomb/idol-mita.html', 'mita')['photos'];

        // page: 3 covers (500x430) + 6 preview strips (380x80) = 9 images, plus the 3 public sample stills the page opens via photodisp()
        self::assertCount(12, $photos);
        self::assertSame(range(1, 12), array_column($photos, 'position'));
        self::assertSame([
            self::BASE . '/idolindex/images/mita03.jpg',
            self::BASE . '/idolindex/images/mita03_s1.jpg',
            self::BASE . '/idolindex/images/mita03_s2.jpg',
            self::BASE . '/sample/images/mita03.jpg',
            self::BASE . '/idolindex/images/mita02.jpg',
            self::BASE . '/idolindex/images/mita02_s1.jpg',
            self::BASE . '/idolindex/images/mita02_s2.jpg',
            self::BASE . '/sample/images/mita02.jpg',
            self::BASE . '/idolindex/images/mita01.jpg',
            self::BASE . '/idolindex/images/mita01_s1.jpg',
            self::BASE . '/idolindex/images/mita01_s2.jpg',
            self::BASE . '/sample/images/mita01.jpg',
        ], array_column($photos, 'image_url'));
        self::assertSame(array_column($photos, 'image_url'), array_column($photos, 'url'));
        self::assertSame(['cover', 'preview', 'preview', 'sample'], array_slice(array_column(array_column($photos, 'metadata'), 'kind'), 0, 4));

        $cover = $photos[0];
        self::assertSame('mita03', $cover['id']);
        self::assertSame(500, $cover['metadata']['width']);
        self::assertSame(430, $cover['metadata']['height']);
        self::assertSame('三田悠貴　超絶やわらかGカップバスト 艶めかしく誘惑する甘い香り', $cover['metadata']['caption']);
        self::assertSame('mita03', $cover['metadata']['pack_id']);
        self::assertSame('三田悠貴', $cover['metadata']['model']);
        self::assertSame('ONE PUBLISHING / ATREE', $cover['metadata']['credit']);
        self::assertSame(self::BASE . '/idolindex/mita.html', $cover['metadata']['source_page_url']);

        $preview = $photos[1];
        self::assertSame(380, $preview['metadata']['width']);
        self::assertSame(80, $preview['metadata']['height']);
        self::assertSame('三田悠貴', $preview['metadata']['caption']);

        $sample = $photos[3];
        self::assertSame('sample_mita03', $sample['id']);
        self::assertSame(self::BASE . '/sample/mita03.html', $sample['metadata']['source_page_url']);
        self::assertSame('超絶やわらかGカップバスト 艶めかしく誘惑する甘い香り', $sample['metadata']['caption']);
    }

    public function test_second_idol_with_voice_content_and_spaced_name(): void
    {
        $gallery = $this->gallery('bomb/idol-tani.html', 'tani');

        self::assertSame('谷 桃子', $gallery['title']);
        self::assertSame(['谷 桃子'], $gallery['performers']);
        self::assertSame('TANI MOMOCO', $gallery['metadata']['name_romaji']);
        self::assertSame(self::BASE . '/idolindex/images/n_tani.gif', $gallery['metadata']['name_image_url']);
        self::assertSame(8, $gallery['photo_count']);
        self::assertSame(['タニモモ', 'セクシー', 'エロス'], array_slice($gallery['tags'], 1, 3));
        self::assertSame([106, 117], array_column($gallery['metadata']['packs'], 'image_count'));
        self::assertSame([30, 30], array_column($gallery['metadata']['packs'], 'voice_count'));
        self::assertSame(['contents' => '画像106点 ムービー3本 システムボイス30点'], $gallery['metadata']['packs'][0]['source_fields']);
        self::assertSame(70, $gallery['photos'][5]['metadata']['height']);
    }

    public function test_page_without_pack_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Gallery($this->clientWithHtml('<html><head><title>BOMB.tvプラス</title></head><body></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/idolindex/none.html', type: CrawlType::Gallery));
    }

    public function test_pack_without_banner_falls_back_to_the_page_title_and_skips_the_footer(): void
    {
        $html = '<html><head><title>BOMB.tvプラス　無名</title></head><body><table><tr><td>'
            . '<img src="images/x01.jpg" width="500" height="430" alt="">'
            . '</td></tr></table></body></html>';

        $gallery = (new Gallery($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::BASE . '/idolindex/x.html', type: CrawlType::Gallery))
            ->meta['gallery'];

        self::assertSame('無名', $gallery['title']);
        self::assertSame(['x01', 'sample_x01'], array_column($gallery['photos'], 'id'));
        self::assertNull($gallery['metadata']['credit']);
        self::assertNull($gallery['metadata']['packs'][0]['price_jpy']);
        self::assertSame([], $gallery['metadata']['packs'][0]['source_fields']);
    }
}
