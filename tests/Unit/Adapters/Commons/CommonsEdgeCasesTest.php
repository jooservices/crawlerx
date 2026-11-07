<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Commons;

use JOOservices\CrawlerX\Adapters\Commons\Markup;
use JOOservices\CrawlerX\Adapters\Commons\Site;
use JOOservices\CrawlerX\Adapters\Commons\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Commons\Types\Listing;
use JOOservices\CrawlerX\Adapters\Commons\Types\PerformerDetail;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class CommonsEdgeCasesTest extends CrawlerXTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CrawlerXFactory::reset();
    }

    public function test_site_target_reads_page_and_api_urls(): void
    {
        self::assertSame(
            ['title' => 'Category:Oto Abe', 'continue' => null, 'limit' => 50],
            Site::target('https://commons.wikimedia.org/wiki/Category:Oto_Abe'),
        );
        self::assertSame(
            ['title' => 'File:Oto Abe 2021.jpg', 'continue' => null, 'limit' => 50],
            Site::target('https://commons.wikimedia.org/wiki/file:Oto_Abe_2021.jpg'),
        );
        self::assertSame(
            ['title' => 'Category:Oto Abe', 'continue' => 'subcat|x|1', 'limit' => 5],
            Site::target('https://commons.wikimedia.org/w/api.php?cmtitle=Category:Oto_Abe&cmcontinue=subcat%7Cx%7C1&cmlimit=5'),
        );
        self::assertSame(50, Site::target('https://commons.wikimedia.org/w/api.php?titles=Category:A&cmlimit=900')['limit']);
        self::assertSame(1, Site::target('https://commons.wikimedia.org/w/api.php?title=Category:A&cmlimit=0')['limit']);
        self::assertNull(Site::target('https://commons.wikimedia.org/wiki/Main_Page'));
        self::assertNull(Site::target('http:///'));
        self::assertSame('https://commons.wikimedia.org/wiki/Category:Oto_Abe', Site::pageUrl('Category:Oto Abe'));
        self::assertSame('https://upload.wikimedia.org/a.jpg', Site::cleanMediaUrl('https://upload.wikimedia.org/a.jpg?utm_source=x&utm_campaign=y'));
        self::assertSame('https://commons.wikimedia.org/wiki/Special:FilePath/A_b.jpg', Site::specialFilePath('A b.jpg'));
    }

    public function test_markup_helpers_clean_commons_fragments(): void
    {
        self::assertNull(Markup::text(null));
        self::assertNull(Markup::text(' <b></b> '));
        self::assertSame('A &amp; B', Markup::text('<a href="x">A &amp;amp; B</a>'));
        self::assertSame('https://x.test/a', Markup::firstLink('<a href="//x.test/a">x</a>'));
        self::assertSame('https://commons.wikimedia.org/wiki/User:A', Markup::firstLink('<a href="/wiki/User:A">x</a>'));
        self::assertSame('https://x.test/?a=1&b=2', Markup::firstLink('<a href="https://x.test/?a=1&amp;b=2">x</a>'));
        self::assertNull(Markup::firstLink('<a href="javascript:void(0)">x</a>'));
        self::assertNull(Markup::firstLink('no link'));
        self::assertNull(Markup::isoDate('2021-02-30'));
        self::assertNull(Markup::isoDate('unknown'));
        self::assertSame('2021-02-28', Markup::isoDate('<time>2021-02-28 10:00</time>'));
        self::assertNull(Markup::isoTimestamp('2021-02-28'));
        self::assertSame('2021-02-28T10:00:00Z', Markup::isoTimestamp('2021-02-28T10:00:00Z'));
    }

    public function test_blocked_and_malformed_api_responses_are_reported(): void
    {
        $url = Site::fileUrl('File:A.jpg');

        FixtureResponder::for('GET', $url)->body('', 200);
        $this->expectBlocked(fn() => $this->gallery($url));

        FixtureResponder::for('GET', $url)->body('{}', 429);
        $this->expectBlocked(fn() => $this->gallery($url));

        FixtureResponder::for('GET', $url)->body('<html>nope</html>', 200);
        $this->expectParseFailure(fn() => $this->gallery($url));

        FixtureResponder::for('GET', $url)->body('{"batchcomplete":true,"query":{"pages":[{"title":"File:A.jpg","missing":true}]}}', 200);
        $this->expectParseFailure(fn() => $this->gallery($url));
    }

    public function test_unsupported_urls_are_rejected_per_capability(): void
    {
        $page = 'https://commons.wikimedia.org/wiki/Main_Page';
        $file = 'https://commons.wikimedia.org/wiki/File:A.jpg';
        $client = FixtureResponder::crawlHttpClient();

        $this->expectParseFailure(fn() => (new Gallery($client))->execute(new CrawlRequestDto(url: $page, type: CrawlType::Gallery)));
        $this->expectParseFailure(fn() => (new Listing($client))->execute(new CrawlRequestDto(url: $file, type: CrawlType::Listing)));
        $this->expectParseFailure(fn() => (new PerformerDetail($client))->execute(new CrawlRequestDto(url: $file, type: CrawlType::PerformerDetail)));
    }

    public function test_an_empty_category_gallery_and_a_file_without_imageinfo_yield_no_photo(): void
    {
        $url = Site::categoryUrl('Category:Empty', 'file', null, 50);
        FixtureResponder::for('GET', $url)->body(
            '{"batchcomplete":true,"continue":{"cmcontinue":"file|abc|9","continue":"-||"},"query":{"categorymembers":[],"pages":[{"pageid":7,"ns":14,"title":"Category:Empty","fullurl":"https://commons.wikimedia.org/wiki/Category:Empty"}]}}',
            200,
        );

        $result = (new Gallery(FixtureResponder::crawlHttpClient()))
            ->execute(new CrawlRequestDto(url: 'https://commons.wikimedia.org/wiki/Category:Empty', type: CrawlType::Gallery, page: 2));

        $gallery = $result->meta['gallery'];
        self::assertSame('Empty', $gallery['title']);
        self::assertSame([], $gallery['photos']);
        self::assertSame(0, $gallery['photo_count']);
        self::assertStringContainsString('cmcontinue=file%7Cabc%7C9', $gallery['metadata']['next_url']);

        $file = Site::fileUrl('File:NoInfo.jpg');
        FixtureResponder::for('GET', $file)->body('{"query":{"pages":[{"pageid":8,"ns":6,"title":"File:NoInfo.jpg"}]}}', 200);
        $this->expectParseFailure(fn() => $this->gallery($file));
    }

    private function gallery(string $url): mixed
    {
        return (new Gallery(FixtureResponder::crawlHttpClient()))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Gallery));
    }

    private function expectBlocked(callable $call): void
    {
        try {
            $call();
            self::fail('Expected a blocked response.');
        } catch (CrawlBlockedException) {
            self::assertTrue(true);
        }
    }

    private function expectParseFailure(callable $call): void
    {
        try {
            $call();
            self::fail('Expected a parse failure.');
        } catch (CrawlParseException) {
            self::assertTrue(true);
        }
    }
}
