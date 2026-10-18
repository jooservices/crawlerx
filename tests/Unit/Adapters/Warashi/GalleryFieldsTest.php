<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Warashi;

use JOOservices\CrawlerX\Adapters\Warashi\Types\Gallery;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class GalleryFieldsTest extends TestCase
{
    public function test_the_gallery_yields_the_related_web_scene(): void
    {
        $g = (new Gallery($this->clientWithFixture('warashi/gallery-yuna-ishikawa.html')))
            ->execute(new CrawlRequestDto(url: 'https://warashi-asian-pornstars.fr/en/s-7-0/yuna-ishikawa/female-asian-pornstar/photo-gallery/56092', type: CrawlType::Gallery))->meta['gallery'];

        $meta = $g['metadata'];
        self::assertSame('caribbeancom', $meta['source']);
        self::assertStringContainsString('click.dtiserv2.com', $meta['source_url']);
        self::assertSame('070723-001', $meta['related_content']['code']);
        self::assertSame('2023-07-07', $meta['related_content']['date']);
        self::assertSame('caribbeancom - カリビアンコム', $meta['related_content']['studio']);
        self::assertSame('洗練された大人のいやし亭 〜絶品ボディといやらしい言葉遣いでおもてなし〜::高垣楓', $meta['related_content']['original_title']);
        self::assertStringContainsString('/web-content/12875', $meta['related_content']['url']);
    }
}
