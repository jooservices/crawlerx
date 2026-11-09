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
        $expected = $this->imageFiles($entity);
        $performer = (new PerformerDetail($this->clientWithMappedResponses($this->responses(
            $fixture,
            'wikidata/labels-Q22128111-1.json',
            'wikidata/labels-Q22128111-2.json',
        ))))->execute(new CrawlRequestDto(
            url: 'https://www.wikidata.org/wiki/Q22128111',
            type: CrawlType::PerformerDetail,
        ))->meta['performer'];

        $images = $performer['metadata']['images'];
        self::assertNotEmpty($expected);
        self::assertSame($expected, array_column($images, 'file'));
        self::assertSame($images[0]['url'], $performer['profile_image_url']);
        foreach ($images as $image) {
            self::assertNotEmpty($image['url']);
            self::assertNotEmpty($image['thumbnail_url']);
        }
    }

    public function test_listing_keeps_the_images_offered_by_each_listed_entity(): void
    {
        $entities = $this->decode('wikidata/entities-list.json')['entities'];
        $expected = [];
        foreach ($entities as $id => $entity) {
            if (is_array($entity)) {
                $expected[$id] = $this->imagePairs($entity);
            }
        }
        $result = (new Listing($this->clientWithMappedResponses($this->responses(
            'wikidata/list-offset-0.json',
            'wikidata/entities-list.json',
        ))))->execute(new CrawlRequestDto(url: Site::listingUrl(0, 5), type: CrawlType::Listing));
        $actual = [];
        foreach ($result->items as $item) {
            $performer = $item->meta['performer'];
            $images = $performer['metadata']['images'] ?? [];
            $actual[$performer['external_id']] = $images;
        }

        self::assertNotEmpty(array_filter($expected));
        self::assertSame($expected, $actual);
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

    /** @param array<string, mixed> $entity
     *  @return list<string>
     */
    private function imageFiles(array $entity): array
    {
        $claims = is_array($entity['claims']['P18'] ?? null) ? $entity['claims']['P18'] : [];

        return array_values(array_filter(array_map(static function (mixed $claim): ?string {
            $value = is_array($claim) ? ($claim['mainsnak']['datavalue']['value'] ?? null) : null;

            return is_string($value) && $value !== '' ? $value : null;
        }, $claims)));
    }

    /**
     * @param array<string, mixed> $entity
     * @return list<array{url: string, thumbnail_url: string, file: string}>
     */
    private function imagePairs(array $entity): array
    {
        return array_map(static function (string $file): array {
            $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode(str_replace(' ', '_', $file));

            return [
                'url' => $url,
                'thumbnail_url' => $url . '?width=320',
                'file' => $file,
            ];
        }, $this->imageFiles($entity));
    }
}
