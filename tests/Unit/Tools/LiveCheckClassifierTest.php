<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckClassifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';

final class LiveCheckClassifierTest extends TestCase
{
    public function test_it_classifies_usable_and_empty_crawl_results(): void
    {
        $classifier = new LiveCheckClassifier();
        $item = new CrawlItemResultDto(
            url: 'https://example.test/detail/1',
            entityType: 'movie',
            meta: ['movie' => ['external_id' => '1', 'title' => 'Synthetic title']],
        );

        self::assertSame('ok', $classifier->classify(CrawlOutcomeDto::success($this->list([$item])))['status']);
        self::assertSame('empty', $classifier->classify(CrawlOutcomeDto::success($this->list([])))['status']);
        self::assertSame('ok', $classifier->classify(CrawlOutcomeDto::success($item))['status']);
        self::assertSame('empty', $classifier->classify(CrawlOutcomeDto::success(
            new CrawlItemResultDto(url: 'https://example.test/detail/2', entityType: 'movie'),
        ))['status']);
    }

    public function test_it_classifies_crawl_failures_by_their_error_code(): void
    {
        $classifier = new LiveCheckClassifier();

        self::assertSame('blocked', $classifier->classify($this->failure(CrawlErrorCode::Blocked))['status']);
        self::assertSame('blocked', $classifier->classify($this->failure(CrawlErrorCode::Challenge))['status']);
        self::assertSame('blocked', $classifier->classify($this->failure(CrawlErrorCode::RateLimited))['status']);
        self::assertSame('parse-error', $classifier->classify($this->failure(CrawlErrorCode::ParseFailed))['status']);
        self::assertSame('parse-error', $classifier->classify($this->failure(CrawlErrorCode::UnsupportedUrl))['status']);
        self::assertSame('empty', $classifier->classify($this->failure(CrawlErrorCode::NotFound))['status']);
        self::assertSame('network', $classifier->classify($this->failure(CrawlErrorCode::Network))['status']);
        self::assertSame('network', $classifier->classify(new RuntimeException('synthetic transport failure'))['status']);
    }

    private function list(array $items): CrawlListResultDto
    {
        return new CrawlListResultDto(
            url: 'https://example.test/list',
            page: 1,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(null, null, null, null, false),
        );
    }

    private function failure(CrawlErrorCode $code): CrawlOutcomeDto
    {
        return CrawlOutcomeDto::failure(new CrawlErrorDto($code, 'synthetic message'));
    }
}
