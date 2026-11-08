<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Wikidata;

use JOOservices\CrawlerX\Adapters\Wikidata\Site;
use JOOservices\CrawlerX\Adapters\Wikidata\Statements;
use JOOservices\CrawlerX\Adapters\Wikidata\Types\Listing;
use JOOservices\CrawlerX\Adapters\Wikidata\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Wikidata\WikidataApi;
use JOOservices\CrawlerX\Adapters\Wikidata\WikidataCrawler;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class WikidataTest extends TestCase
{
    private const JSON = ['Content-Type' => 'application/json'];

    private const LISTED = ['Q100715228', 'Q102247563', 'Q10295539', 'Q10313825', 'Q10358931'];

    public function test_listing_yields_one_performer_per_listed_item_in_query_order(): void
    {
        $url = Site::listingUrl(0, 5);
        $result = (new Listing($this->clientWithMappedResponses([
            $url => $this->json('wikidata/list-offset-0.json'),
            Site::entitiesUrl(self::LISTED) => $this->json('wikidata/entities-list.json'),
        ])))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing));

        self::assertSame('performer', $result->entityType);
        self::assertCount(5, $result->items);
        self::assertSame(
            self::LISTED,
            array_map(static fn($item): string => $item->meta['performer']['external_id'], $result->items),
        );

        $first = $result->items[0]->meta['performer'];
        self::assertSame('Kana Sayuki', $first['name']);
        self::assertSame('咲雪華奈', $first['name_japanese']);
        self::assertSame('https://www.wikidata.org/wiki/Q100715228', $first['url']);
        self::assertSame(['Sayuki Kana'], $first['aliases']);
        self::assertSame('日本のAV女優', $first['raw_profile']['description_ja']);
        self::assertSame('Japanese pornographic actress', $first['raw_profile']['description_en']);
        self::assertNotEmpty($first['metadata']['statements']);

        self::assertSame(1, $result->page);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(Site::listingUrl(5, 5), $result->pagination->nextUrl);
    }

    public function test_the_last_listing_page_has_no_next_page(): void
    {
        $url = Site::listingUrl(0, 50);
        $result = (new Listing($this->clientWithMappedResponses([
            $url => ['body' => '{"results":{"bindings":[]}}', 'headers' => self::JSON],
        ])))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Listing));

        self::assertSame([], $result->items);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
    }

    public function test_detail_maps_every_profile_field_and_image(): void
    {
        $performer = $this->detail();

        self::assertSame('Q22128111', $performer['external_id']);
        self::assertSame('Yua Mikami', $performer['name']);
        self::assertSame('三上悠亜', $performer['name_japanese']);
        self::assertSame('https://www.wikidata.org/wiki/Q22128111', $performer['url']);
        self::assertSame('1993-08-16', $performer['birth_date_raw']);
        self::assertSame('159 cm', $performer['height_raw']);
        self::assertSame(['Momona Kito', 'Mikami Yua', '鬼頭桃菜', '三上 悠亜', '鬼頭 桃菜'], $performer['aliases']);
        self::assertSame(['Japanese idol', 'AV actress', 'VTuber'], $performer['tags']);
        self::assertSame(
            'https://commons.wikimedia.org/wiki/Special:FilePath/Mikami_Yua_to_K-POP_CIMG6925_%28cropped%29.jpg',
            $performer['profile_image_url'],
        );

        $profile = $performer['raw_profile'];
        self::assertSame('Nagoya', $profile['birth_place']);
        self::assertSame('female', $profile['gender']);
        self::assertSame(['Japan'], $profile['citizenship']);
        self::assertSame('https://miss.co.jp/', $profile['official_website']);
        self::assertSame('日本の女性タレント、元AV女優', $profile['description_ja']);
        self::assertSame('Japanese singer and former AV actress (born 1993)', $profile['description_en']);

        $metadata = $performer['metadata'];
        self::assertSame('Q22128111', $metadata['wikidata_id']);
        self::assertSame(['ja' => '三上悠亜', 'en' => 'Yua Mikami'], $metadata['labels']);
        self::assertSame('https://en.wikipedia.org/wiki/Yua_Mikami', $metadata['sitelinks']['enwiki']);
        self::assertSame('https://ja.wikipedia.org/wiki/%E4%B8%89%E4%B8%8A%E6%82%A0%E4%BA%9C', $metadata['sitelinks']['jawiki']);
        self::assertCount(1, $metadata['images']);
        self::assertSame('Mikami Yua to K-POP CIMG6925 (cropped).jpg', $metadata['images'][0]['file']);
        self::assertSame(
            'https://commons.wikimedia.org/wiki/Special:FilePath/Mikami_Yua_to_K-POP_CIMG6925_%28cropped%29.jpg?width=320',
            $metadata['images'][0]['thumbnail_url'],
        );
    }

    public function test_no_statement_is_dropped_and_all_properties_are_labelled(): void
    {
        $statements = $this->detail()['metadata']['statements'];
        $entity = WikidataApi::entities($this->decode('wikidata/entity-Q22128111.json'))['Q22128111'];

        self::assertCount(count((array) $entity['claims']), $statements);
        foreach ($statements as $statement) {
            self::assertIsString($statement['label'] ?? null, (string) $statement['property']);
            self::assertNotEmpty($statement['values']);
        }

        $byProperty = array_column($statements, null, 'property');
        self::assertSame('female', $byProperty['P21']['values'][0]['value']['label']);
        self::assertSame(['date' => '1993-08-16', 'precision' => 11], $byProperty['P569']['values'][0]['value']);
        self::assertSame(['amount' => '159', 'unit' => 'Q174728', 'unit_label' => 'centimetre'], $byProperty['P2048']['values'][0]['value']);
    }

    public function test_referenced_ids_cover_properties_items_and_units_and_fit_the_request_limit(): void
    {
        $entity = WikidataApi::entities($this->decode('wikidata/entity-Q22128111.json'))['Q22128111'];
        $ids = Statements::referencedIds($entity);

        self::assertContains('P569', $ids);
        self::assertContains('Q6581072', $ids);
        self::assertContains('Q174728', $ids);
        self::assertNotContains('Q22128111', $ids);
        self::assertSame(array_values(array_unique($ids)), $ids);
        self::assertLessThanOrEqual(2 * Site::MAX_IDS, count($ids));
    }

    public function test_detail_of_a_missing_item_and_a_blocked_response_fail_clearly(): void
    {
        $missing = Site::entitiesUrl(['Q1']);
        $client = $this->clientWithMappedResponses([
            $missing => ['body' => '{"entities":{"Q1":{"id":"Q1","missing":""}}}', 'headers' => self::JSON],
        ]);
        try {
            (new PerformerDetail($client))->execute(new CrawlRequestDto(url: Site::BASE_URL . '/wiki/Q1', type: CrawlType::PerformerDetail));
            self::fail('A missing item must not produce a performer.');
        } catch (CrawlParseException $exception) {
            self::assertStringContainsString('Q1', $exception->getMessage());
        }

        $blocked = $this->clientWithMappedResponses([$missing => ['body' => '', 'status' => 429, 'headers' => self::JSON]]);
        $this->expectException(CrawlBlockedException::class);
        (new PerformerDetail($blocked))->execute(new CrawlRequestDto(url: Site::BASE_URL . '/wiki/Q1', type: CrawlType::PerformerDetail));
    }

    public function test_detail_needs_an_item_url_and_listing_needs_the_query_service(): void
    {
        $client = $this->clientWithMappedResponses([]);
        try {
            (new PerformerDetail($client))->execute(new CrawlRequestDto(url: Site::BASE_URL . '/wiki/Special:Search', type: CrawlType::PerformerDetail));
            self::fail('A non-item URL must be rejected.');
        } catch (CrawlParseException) {
            self::assertTrue(true);
        }

        $this->expectException(CrawlParseException::class);
        (new Listing($client))->execute(new CrawlRequestDto(url: Site::BASE_URL . '/wiki/Q1', type: CrawlType::Listing));
    }

    public function test_site_helpers_read_ids_offsets_and_limits(): void
    {
        self::assertSame('Q22128111', Site::qid('https://www.wikidata.org/wiki/Q22128111'));
        self::assertSame('Q22128111', Site::qid('https://www.wikidata.org/wiki/Special:EntityData/Q22128111.json'));
        self::assertSame('Q22128111', Site::qid(Site::entitiesUrl(['Q22128111'])));
        self::assertNull(Site::qid('https://www.wikidata.org/wiki/Special:Search'));
        self::assertNull(Site::qid(Site::entitiesUrl(['Q1', 'Q2'])));

        self::assertTrue(Site::isListing(Site::listingUrl()));
        self::assertFalse(Site::isListing(Site::entitiesUrl(['Q1'])));
        self::assertSame(100, Site::offset(Site::listingUrl(100, 50)));
        self::assertSame(50, Site::limit(Site::listingUrl(100, 50)));
        self::assertSame(0, Site::offset('https://query.wikidata.org/sparql'));
        self::assertSame(Site::DEFAULT_LIMIT, Site::limit('https://query.wikidata.org/sparql'));
        self::assertSame(
            'https://commons.wikimedia.org/wiki/Special:FilePath/A_b%28c%29.jpg',
            Site::fileUrl('A b(c).jpg'),
        );
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('wikidata');
        self::assertInstanceOf(WikidataCrawler::class, $crawler);

        $expected = [
            Site::listingUrl() => CrawlType::Listing,
            'https://www.wikidata.org/wiki/Q22128111' => CrawlType::PerformerDetail,
            'https://www.wikidata.org/wiki/Special:EntityData/Q22128111.json' => CrawlType::PerformerDetail,
            Site::entitiesUrl(['Q22128111']) => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $url => $type) {
            $detected = $crawler->detectUrl($url);
            self::assertTrue($detected->matched, $url);
            self::assertSame($type, $detected->crawlType, $url);
        }

        self::assertFalse($crawler->detectUrl('https://www.wikidata.org/wiki/Special:Search')->matched);
        self::assertSame('wikidata', $crawler->name());
    }

    /**
     * @return array{body: string, headers: array<string, string>}
     */
    private function json(string $fixture): array
    {
        return ['body' => $this->loadFixture($fixture), 'headers' => self::JSON];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $fixture): array
    {
        $decoded = json_decode($this->loadFixture($fixture), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return WikidataApi::assoc($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(): array
    {
        $result = (new PerformerDetail($this->clientWithMappedResponses([
            Site::entitiesUrl(['Q22128111']) => $this->json('wikidata/entity-Q22128111.json'),
            $this->labelsUrl(1) => $this->json('wikidata/labels-Q22128111-1.json'),
            $this->labelsUrl(2) => $this->json('wikidata/labels-Q22128111-2.json'),
        ])))->execute(new CrawlRequestDto(url: Site::BASE_URL . '/wiki/Q22128111', type: CrawlType::PerformerDetail));

        self::assertSame('performer', $result->entityType);

        return $result->meta['performer'];
    }

    private function labelsUrl(int $chunk): string
    {
        $entity = WikidataApi::entities($this->decode('wikidata/entity-Q22128111.json'))['Q22128111'];

        return Site::labelsUrl(array_chunk(Statements::referencedIds($entity), Site::MAX_IDS)[$chunk - 1]);
    }
}
