<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Support;

use JOOservices\CrawlerX\Support\ChallengeResponseDetector;
use PHPUnit\Framework\TestCase;

final class ChallengeResponseDetectorTest extends TestCase
{
    public function test_detects_cf_mitigated_header_case_insensitively(): void
    {
        self::assertTrue(ChallengeResponseDetector::isChallenge('', 200, [
            'Cf-Mitigated' => 'challenge',
        ]));
    }

    public function test_joins_multiple_cf_mitigated_header_values(): void
    {
        self::assertTrue(ChallengeResponseDetector::isChallenge('', 200, [
            'cf-mitigated' => ['managed', 'challenge'],
        ]));
    }

    public function test_ignores_unrelated_headers_when_no_challenge_is_present(): void
    {
        self::assertFalse(ChallengeResponseDetector::isChallenge(
            '<html><body>Available</body></html>',
            200,
            ['content-type' => 'text/html'],
        ));
    }
}
