<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

/**
 * Shared plumbing of the image-completeness tests: run one adapter type over a real captured fixture and count the
 * image URLs the DTO carries, so that every image slot of a page stays covered.
 */
abstract class ImageAuditTestCase extends TestCase
{
    /**
     * @param  class-string  $type
     */
    protected function item(string $type, string $fixture, string $url, CrawlType $crawlType): CrawlItemResultDto
    {
        return (new $type($this->clientWithFixture($fixture)))->execute(new CrawlRequestDto(url: $url, type: $crawlType));
    }

    /**
     * @param  class-string  $type
     */
    protected function list(string $type, string $fixture, string $url, CrawlType $crawlType): CrawlListResultDto
    {
        return (new $type($this->clientWithFixture($fixture)))->execute(new CrawlRequestDto(url: $url, type: $crawlType));
    }

    /**
     * The values of one key over every row, nulls and empty strings dropped.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    protected function column(array $rows, string $key): array
    {
        $values = array_column($rows, $key);

        return array_values(array_filter($values, static fn(mixed $value): bool => is_string($value) && $value !== ''));
    }

    /**
     * @param  list<CrawlItemResultDto>  $items
     * @return list<array<string, mixed>>
     */
    protected function metaOf(array $items, string $entity): array
    {
        return array_map(static fn(CrawlItemResultDto $item): array => $item->meta[$entity], $items);
    }
}
