<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckClassifier;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';

final class LiveCheckClassifierFailureTest extends TestCase
{
    public function test_unknown_crawl_outcomes_are_parse_errors(): void
    {
        $classifier = new LiveCheckClassifier();
        $unknown = CrawlOutcomeDto::failure(new CrawlErrorDto(CrawlErrorCode::Unknown, 'synthetic parser failure'));

        self::assertSame('parse-error', $classifier->classify($unknown)['status']);
    }

    public function test_transport_exceptions_and_network_outcomes_remain_network_failures(): void
    {
        $classifier = new LiveCheckClassifier();
        $networkException = new NetworkConnectionException(new Request('GET', 'https://example.test'), 'synthetic network failure');
        $networkOutcome = CrawlOutcomeDto::failure(new CrawlErrorDto(CrawlErrorCode::Network, 'synthetic network failure'));

        self::assertSame('network', $classifier->classify($networkException)['status']);
        self::assertSame('network', $classifier->classify($networkOutcome)['status']);
    }
}
