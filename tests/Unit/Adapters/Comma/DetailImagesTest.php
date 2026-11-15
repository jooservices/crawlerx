<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Comma;

use Faker\Factory;
use JOOservices\CrawlerX\Adapters\Comma\DetailImages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

final class DetailImagesTest extends TestCase
{
    public function test_gallery_pairs_full_size_links_with_thumbnails_and_keeps_bare_images(): void
    {
        $faker = Factory::create();
        $base = 'https://' . $faker->domainName();
        $full = '/wp-content/uploads/' . $faker->unique()->slug() . '.jpg';
        $thumb = '/wp-content/uploads/' . $faker->unique()->slug() . '_s.jpg';
        $bare = '/wp-content/uploads/' . $faker->unique()->slug() . '.png';
        $root = $this->root(
            '<li><a href="' . $full . '"><img src="' . $thumb . '" alt=""></a></li>'
            . '<li><img src="' . $bare . '" alt=""></li>',
            $base,
        );

        $screenshots = (new DetailImages())->screenshots($root, $base . '/dvd/x/', null);

        self::assertSame([
            ['url' => $base . $full, 'thumbnail_url' => $base . $thumb],
            ['url' => $base . $bare, 'thumbnail_url' => null],
        ], $screenshots);
    }

    public function test_a_link_that_is_not_an_image_file_falls_back_to_the_thumbnail(): void
    {
        $faker = Factory::create();
        $base = 'https://' . $faker->domainName();
        $thumb = '/wp-content/uploads/' . $faker->unique()->slug() . '.jpg';
        $root = $this->root('<li><a href="/product/' . $faker->unique()->slug() . '/"><img src="' . $thumb . '"></a></li>', $base);

        $screenshots = (new DetailImages())->screenshots($root, $base . '/dvd/x/', null);

        self::assertSame([['url' => $base . $thumb, 'thumbnail_url' => null]], $screenshots);
    }

    public function test_cover_comes_first_and_an_image_listed_twice_is_kept_once(): void
    {
        $faker = Factory::create();
        $base = 'https://' . $faker->domainName();
        $cover = $base . '/wp-content/uploads/' . $faker->unique()->slug() . '.png';
        $gallery = '/wp-content/uploads/' . $faker->unique()->slug() . '.jpg';
        $root = $this->root('<li><img src="' . $cover . '"></li><li><img src="' . $gallery . '"></li><li><img src="' . $gallery . '"></li>', $base);

        $screenshots = (new DetailImages())->screenshots($root, $base . '/dvd/x/', $cover);

        self::assertSame([
            ['url' => $cover, 'thumbnail_url' => null],
            ['url' => $base . $gallery, 'thumbnail_url' => null],
        ], $screenshots);
    }

    public function test_without_a_gallery_or_cover_there_are_no_screenshots(): void
    {
        $root = new Crawler('<section class="inner pt-5"><h1>x</h1></section>', 'https://example.test/');

        self::assertSame([], (new DetailImages())->screenshots($root, 'https://example.test/dvd/x/', null));
    }

    private function root(string $gallery, string $base): Crawler
    {
        return new Crawler(
            '<section class="inner pt-5"><h3><span>ギャラリー</span></h3><ul class="d-flex flex-wrap w-100">' . $gallery . '</ul></section>',
            $base . '/dvd/x/',
        );
    }
}
