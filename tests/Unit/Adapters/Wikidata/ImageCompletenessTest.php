<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Wikidata;

use JOOservices\CrawlerX\Adapters\Wikidata\Site;
use JOOservices\CrawlerX\Adapters\Wikidata\Types\Listing;
use JOOservices\CrawlerX\Adapters\Wikidata\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    private const JSON = ['Content-Type' => 'application/json'];

    public function test_detail_keeps_each_image_statement_as_a_full_image_and_thumbnail_pair(): void
    {
        $fixture = 'wikidata/entity-Q22128111.json';
        $payload = $this->decode($fixture);
        $entity = $payload['entities']['Q22128111'];
        $expected = $this->imageStatements($entity);
        $performer = (new PerformerDetail($this->clientWithMappedResponses($this->responses(
            $fixture,
            'wikidata/labels-Q22128111-1.json',
            'wikidata/labels-Q22128111-2.json',
        ))))->execute(new CrawlRequestDto(
            url: 'https://www.wikidata.org/wiki/Q22128111',
            type: CrawlType::PerformerDetail,
        ))->meta['performer'];

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($performer['metadata']['images']));
        self::assertGreaterThanOrEqual($expected, count($this->column($performer['metadata']['images'], 'url')));
        self::assertGreaterThanOrEqual($expected, count($this->column($performer['metadata']['images'], 'thumbnail_url')));
        self::assertNotEmpty($performer['profile_image_url']);
    }

    public function test_listing_keeps_the_images_offered_by_each_listed_entity(): void
    {
        $entities = $this->decode('wikidata/entities-list.json')['entities'];
        $expected = array_sum(array_map(fn(array $entity): int => $this->imageStatements($entity), array_filter($entities, 'is_array')));
        $result = (new Listing($this->clientWithMappedResponses($this->responses(
            'wikidata/list-offset-0.json',
            'wikidata/entities-list.json',
        ))))->execute(new CrawlRequestDto(url: Site::listingUrl(0, 5), type: CrawlType::Listing));
        $performers = $this->metaOf($result->items, 'performer');
        $images = array_merge(...array_map(
            static fn(array $performer): array => is_array($performer['metadata']['images'] ?? null) ? $performer['metadata']['images'] : [],
            $performers,
        ));

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count($images));
        self::assertGreaterThanOrEqual($expected, count($this->column($images, 'url')));
        self::assertGreaterThanOrEqual($expected, count($this->column($images, 'thumbnail_url')));
    }

    /** @return array<string, array{body: string, headers: array<string, string>}> */
    private function responses(string ...$fixtures): array
    {
        $responses = [];
        foreach ($fixtures as $fixture) {
            $metadata = json_decode($this->loadFixture($fixture . '.meta.json'), true, flags: JSON_THROW_ON_ERROR);
            $responses[$metadata['source_url']] = ['body' => $this->loadFixture($fixture), 'headers' => self::JSON];
        }

        return $responses;
    }

    /** @return array<string, mixed> */
    private function decode(string $fixture): array
    {
        return json_decode($this->loadFixture($fixture), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $entity */
    private function imageStatements(array $entity): int
    {
        $claims = is_array($entity['claims']['P18'] ?? null) ? $entity['claims']['P18'] : [];

        return count(array_filter($claims, static function (mixed $claim): bool {
            $value = is_array($claim) ? ($claim['mainsnak']['datavalue']['value'] ?? null) : null;

            return is_string($value) && $value !== '';
        }));
    }
}
