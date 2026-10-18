<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Heyzo;

use JOOservices\CrawlerX\Adapters\Heyzo\Types\Detail;
use JOOservices\CrawlerX\Adapters\Heyzo\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/heyzo/';

    public function test_the_movie_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . 'detail.html'));

        self::assertSame('3927', $movie['external_id']);
        self::assertSame('HEYZO-3927', $movie['code']);
        self::assertSame('Lewd Frolics with Sexy MILFs', $movie['title']);
        self::assertSame('https://en.heyzo.com/contents/3000/3927/images/player_thumbnail_en.jpg', $movie['cover_url']);
        self::assertSame('2026-08-29', $movie['date']);
        self::assertSame(54, $movie['duration']);
        self::assertSame(
            ['Glabrousness', 'MILF', 'Riding', 'Doggy Style', '69', 'Finger Fuck', 'Creampie', 'Cunnilingus', 'Masturbation', 'Bath', 'Kimono'],
            $movie['tags'],
        );

        self::assertSame(['1181', '1607'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['Miki Hoshino', 'Nagisa Kazama'], array_column($movie['performers'], 'name'));
        self::assertSame('https://en.heyzo.com/listpages/actor_1181_1.html', $movie['performers'][0]['url']);

        $meta = $movie['metadata'];
        self::assertSame(['score' => 4.5, 'scale' => 5.0, 'votes' => 2], $meta['rating']);
        self::assertArrayNotHasKey('series', $meta);
        self::assertSame('https://sample.heyzo.com/contents/3000/3927/sample.mp4', $meta['sample_video_url']);
        self::assertSame('2026-08-29', $meta['release_date_raw']);
        self::assertSame('2018-07-28', $meta['original_release_date']);
        self::assertSame('2026-08-29', $meta['upload_date']);
        self::assertSame(['Glabrousness', 'MILF'], $meta['tag_groups']['Type']);
        self::assertSame(['Bath', 'Kimono'], $meta['tag_groups']['Theme']);
        self::assertArrayNotHasKey('fields', $meta);

        self::assertSame(['Mobile', 'HD Lite', 'FULL HD'], array_column($meta['downloads'], 'quality'));
        self::assertSame(['0.19GB', '0.79GB', '1.93GB'], array_column($meta['downloads'], 'size_label'));
        self::assertSame('960x540', $meta['downloads'][1]['resolution']);
        self::assertSame('5.2Mbps', $meta['downloads'][2]['bitrate']);
        self::assertSame(54, $meta['downloads'][0]['duration']);

        self::assertCount(3, $movie['screenshots']);
        self::assertSame('https://en.heyzo.com/contents/3000/3927/gallery/001.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://en.heyzo.com/contents/3000/3927/gallery/thumbnail_001.jpg', $movie['screenshots'][0]['thumbnail_url']);

        self::assertCount(11, $meta['chapters']);
        self::assertSame(['time_seconds' => 470, 'label' => 'Blowjob', 'image_url' => 'https://en.heyzo.com/contents/3000/3927/images/capture1.jpg'], $meta['chapters'][0]);
    }

    public function test_sidebar_filter_lists_are_not_the_movies_data(): void
    {
        $movie = $this->detail((string) file_get_contents(self::FIXTURES . 'detail.html'));

        foreach (['A', 'B', 'PORN STAR', 'Porn Star'] as $noise) {
            self::assertNotContains($noise, array_column($movie['performers'], 'name'));
        }
        self::assertNotContains('Nice Booty', $movie['tags']);
        self::assertArrayNotHasKey('Sex Styles', $movie['metadata']);
    }

    public function test_a_series_and_unknown_rows_are_kept(): void
    {
        $html = '<html><body><script type="application/ld+json">{"@type":"Movie","name":"Name","duration":"01:00:00","dateCreated":"2020-01-02"}</script>'
            . '<table class="movieInfo">'
            . '<tr><td>Series</td><td>Series One</td></tr>'
            . '<tr><td>Special</td><td>extra</td></tr>'
            . '</table></body></html>';

        $movie = $this->detail($html);

        self::assertSame('Series One', $movie['metadata']['series']);
        self::assertSame(['Special' => 'extra'], $movie['metadata']['fields']);
        self::assertSame('2020-01-02', $movie['date']);
        self::assertSame(60, $movie['duration']);
    }

    public function test_values_written_by_page_scripts_are_read_when_the_markup_lacks_them(): void
    {
        $script = <<<'JS'
var movie_obj = {"name":"Script Title","image":"//en.heyzo.com/contents/3000/3927/images/player_thumbnail_en.jpg","duration":"00:54:12","dateCreated":"2026-08-29","video":{"uploadDate":"2026-08-29"}};
var aggregateRating = {"ratingValue":"4.5","bestRating":"5","reviewCount":"2"};
var dir_gallery = "/member/";
var dir_gallery = "/contents/3000/3927/gallery/";
a = dir_gallery+'001.jpg'; b = dir_gallery+'002.jpg';
var movieSizeGB = {'mobile':'0.19GB','hd-light':'0.79GB','standard':'1.00GB','hd':'1.93GB'};
JS;
        $movie = $this->detail('<html><body><script>' . $script . '</script></body></html>');

        self::assertSame('Script Title', $movie['title']);
        self::assertSame(54, $movie['duration']);
        self::assertSame(['score' => 4.5, 'scale' => 5.0, 'votes' => 2], $movie['metadata']['rating']);
        self::assertSame('https://en.heyzo.com/contents/3000/3927/gallery/002.jpg', $movie['screenshots'][1]['url']);
        self::assertSame(['0.19GB', '0.79GB', '1.93GB'], array_column($movie['metadata']['downloads'], 'size_label'));
    }

    public function test_listing_cards_carry_cover_duration_date_performer_and_type(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing.html'))))
            ->execute(new CrawlRequestDto(url: 'https://en.heyzo.com/listpages/all_1.html', type: CrawlType::Listing));

        self::assertCount(30, $result->items);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('3933', $first['external_id']);
        self::assertNull($first['title']);
        self::assertSame('HEYZO-3933', $first['code']);
        self::assertSame('https://en.heyzo.com/contents/3000/3933/images/player_thumbnail_358_en.jpg', $first['cover_url']);
        self::assertSame('2026-09-08', $first['date']);
        self::assertSame(62, $first['duration']);
        self::assertSame('1368', $first['performers'][0]['external_id']);
        self::assertSame('Kana Sasaki', $first['performers'][0]['name']);
        self::assertSame('Porn Star', $first['metadata']['performer_type']);
        self::assertSame('https://en.heyzo.com/moviepages/3933/index.html', $result->items[0]->url);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame('https://en.heyzo.com/listpages/all_2.html', $result->pagination->nextUrl);
    }

    public function test_a_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body></body></html>')))
            ->execute(new CrawlRequestDto(url: 'https://en.heyzo.com/listpages/all_1.html', type: CrawlType::Listing));
    }

    /** @return array<string, mixed> */
    private function detail(string $html): array
    {
        return (new Detail($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: 'https://en.heyzo.com/moviepages/3927/index.html', type: CrawlType::Detail))
            ->meta['movie'];
    }
}
