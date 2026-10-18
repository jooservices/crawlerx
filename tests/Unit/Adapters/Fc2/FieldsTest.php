<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Fc2;

use JOOservices\CrawlerX\Adapters\Fc2\Types\Detail;
use JOOservices\CrawlerX\Adapters\Fc2\Types\Listing;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Http\PsrCrawlHttpResponse;
use JOOservices\CrawlerX\Tests\TestCase;
use Nyholm\Psr7\Response;

final class FieldsTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/fc2/';

    private const URL = 'https://adult.contents.fc2.com/article/4968399/';

    private const WIDGET = 'https://adult.contents.fc2.com/widget/article/4968399/description?ac=81f4998a282e2a88d691ff3bbe72a3fc';

    public function test_the_product_page_yields_every_field_it_offers(): void
    {
        $movie = $this->detail(widget: '<html><body><div data-container><p>Synopsis line one.</p><p>Line two.</p></div></body></html>');

        self::assertSame('4968399', $movie['external_id']);
        self::assertSame('FC2-PPV-4968399', $movie['code']);
        self::assertStringStartsWith('【数量限定公開】可愛らしい雰囲気の人妻公務員', $movie['title']);
        self::assertSame('https://storage202000.contents.fc2.com/file/399/39896198/1787909480.39.png', $movie['cover_url']);
        self::assertSame('Synopsis line one.Line two.', $movie['description']);
        self::assertSame('2026-08-28', $movie['date']);
        self::assertSame(40, $movie['duration']);
        self::assertCount(15, $movie['tags']);
        self::assertSame(['ハメ撮り', '素人', '美乳'], array_slice($movie['tags'], 0, 3));
        self::assertNotContains('Limited product list', $movie['tags']);

        $meta = $movie['metadata'];
        self::assertSame('全日本極上素人俱楽部', $meta['seller']);
        self::assertSame('https://adult.contents.fc2.com/users/gokujyou-shiroutoclub/', $meta['seller_url']);
        self::assertSame(80, $meta['seller_followers']);
        self::assertSame(['score' => 5.0, 'scale' => 5.0, 'votes' => 13], $meta['rating']);
        self::assertSame(['amount' => 900.0, 'currency' => 'pt', 'label' => '900pt'], $meta['price']);
        self::assertSame(['amount' => 3000.0, 'currency' => 'pt', 'label' => '3000pt'], $meta['list_price']);
        self::assertSame(70, $meta['discount_percent']);
        self::assertSame('2026-09-10', $meta['sale_end_date']);
        self::assertSame(4, $meta['remaining']);
        self::assertSame('LimitedAvailability', $meta['availability']);
        self::assertSame(['PC', 'iOS', 'Android'], $meta['supported_devices']);
        self::assertSame('FC2 PPV 4968399', $meta['product_id_label']);
        self::assertSame('2026/08/28', $meta['release_date_raw']);

        self::assertCount(5, $movie['screenshots']);
        self::assertSame('https://contents-thumbnail2.fc2.com/w1280/storage202000.contents.fc2.com/file/399/39896198/1787975191.76.png', $movie['screenshots'][0]['url']);
        self::assertSame('https://contents-thumbnail2.fc2.com/w480/storage202000.contents.fc2.com/file/399/39896198/1787975191.76.png', $movie['screenshots'][0]['thumbnail_url']);

        self::assertCount(13, $meta['reviews']);
        self::assertSame('LGQ******', $meta['reviews'][0]['reviewer']);
        self::assertSame('13 hours ago', $meta['reviews'][0]['posted']);
        self::assertSame('かわいい人ですね。興奮します。', $meta['reviews'][0]['text']);
        self::assertSame(5, $meta['reviews'][0]['rating']);
        self::assertSame('興奮しました。', $meta['reviews'][0]['headline']);
    }

    public function test_the_japanese_page_gives_the_same_date_and_stock(): void
    {
        $html = '<html><body><div class="label_limite_001"><span>この商品は数に限りがあります。</span><span>残り1個です</span></div>'
            . '<section class="items_article_headerInfo"><h3>Title</h3></section>'
            . '<div class="items_article_softDevice"><p>販売日 : 2026/08/28</p></div>'
            . '<div class="items_article_softDevice"><p>商品ID : FC2 PPV 4968399</p></div>'
            . '<script type="application/ld+json">{"@type":"Product","name":"Title","productID":"4968399","image":{"url":"https://example.test/a.png"}}</script>'
            . '</body></html>';

        $movie = (new Detail($this->clientWithMappedResponses([self::URL => ['body' => $html]])))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Detail))
            ->meta['movie'];

        self::assertSame('2026-08-28', $movie['date']);
        self::assertSame('FC2 PPV 4968399', $movie['metadata']['product_id_label']);
        self::assertSame(1, $movie['metadata']['remaining']);
    }

    public function test_the_description_request_carries_the_session_cookies_of_the_product_page(): void
    {
        $client = new class ((string) file_get_contents(self::FIXTURES . 'detail.html')) implements CrawlHttpClient {
            /** @var list<array{url: string, headers: array<string, string>}> */
            public array $requests = [];

            public function __construct(private readonly string $page)
            {
            }

            public function get(string $url, array $headers = []): CrawlHttpResponse
            {
                $this->requests[] = ['url' => $url, 'headers' => $headers];

                return str_contains($url, '/widget/')
                    ? new PsrCrawlHttpResponse(new Response(200, [], '<html><body><div data-container><p>Synopsis.</p></div></body></html>'))
                    : new PsrCrawlHttpResponse(new Response(200, ['Set-Cookie' => ['CONTENTS_FC2_PHPSESSID=abc; path=/; HttpOnly', 'contents_mode=adult; path=/']], $this->page));
            }
        };

        $movie = (new Detail($client))->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Detail))->meta['movie'];

        self::assertSame('Synopsis.', $movie['description']);
        self::assertCount(2, $client->requests);
        self::assertSame(self::WIDGET, $client->requests[1]['url']);
        self::assertSame('CONTENTS_FC2_PHPSESSID=abc; contents_mode=adult', $client->requests[1]['headers']['Cookie']);
        self::assertSame(self::URL, $client->requests[1]['headers']['Referer']);
    }

    public function test_hidden_honeypot_text_and_scripts_are_not_part_of_the_description(): void
    {
        $widget = '<html><body><script>window["x"]="MC5i";</script><div data-container><p>Real synopsis.</p>'
            . '<p style="height:0;color:#fff;overflow:hidden;">MC5iYmJkNjJmMS40YmNm</p><p style="opacity: 0;">MC5iYmJkNjJmMS40YmNmY2Yu</p>'
            . '<p>MC5iYmJkNjJmMS40YmNmY2YuNmFkNTI0YzQ=</p><p>Second line.</p></div></body></html>';

        self::assertSame('Real synopsis.Second line.', $this->detail(widget: $widget)['description']);
    }

    public function test_a_translation_notice_is_not_a_description(): void
    {
        self::assertNull($this->detail(widget: '<html><body><div data-container><p>Currently, we are preparing for translation. Please wait a moment.</p></div></body></html>')['description']);
    }

    public function test_a_failed_description_widget_leaves_the_description_empty(): void
    {
        self::assertNull($this->detail(widget: '<html><body><div data-container><p>読み込みに失敗しました。</p></div></body></html>')['description']);
        self::assertNull($this->detail(widget: null)['description']);
    }

    public function test_listing_cards_carry_title_cover_duration_price_stock_favorites_and_seller(): void
    {
        $result = (new Listing($this->clientWithHtml((string) file_get_contents(self::FIXTURES . 'listing.html'))))
            ->execute(new CrawlRequestDto(url: 'https://adult.contents.fc2.com/', type: CrawlType::Listing));

        self::assertCount(45, $result->items);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('4971756', $first['external_id']);
        self::assertSame('FC2-PPV-4971756', $first['code']);
        self::assertStringStartsWith('【野獣妻】', $first['title']);
        self::assertSame('https://contents-thumbnail2.fc2.com/w360/storage201000.contents.fc2.com/file/389/38893931/1788425526.79.jpg', $first['cover_url']);
        self::assertSame(59, $first['duration']);
        self::assertSame(1490.0, $first['metadata']['price']['amount']);
        self::assertSame(2980.0, $first['metadata']['list_price']['amount']);
        self::assertSame(50, $first['metadata']['discount_percent']);
        self::assertSame(2, $first['metadata']['remaining']);
        self::assertSame(2, $first['metadata']['sale_days_left']);
        self::assertSame(125, $first['metadata']['favorite_count']);
        self::assertSame(31, $first['metadata']['comment_count']);
        self::assertSame('アノン', $first['metadata']['seller']);
        self::assertSame('https://adult.contents.fc2.com/article/4971756/', $result->items[0]->url);
    }

    public function test_a_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body></body></html>')))
            ->execute(new CrawlRequestDto(url: 'https://adult.contents.fc2.com/', type: CrawlType::Listing));
    }

    /** @return array<string, mixed> */
    private function detail(?string $widget): array
    {
        $responses = [self::URL => ['body' => (string) file_get_contents(self::FIXTURES . 'detail.html')]];
        if ($widget !== null) {
            $responses[self::WIDGET] = ['body' => $widget];
        }

        return (new Detail($this->clientWithMappedResponses($responses)))
            ->execute(new CrawlRequestDto(url: self::URL, type: CrawlType::Detail))
            ->meta['movie'];
    }
}
