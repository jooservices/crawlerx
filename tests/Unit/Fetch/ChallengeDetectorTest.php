<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use PHPUnit\Framework\TestCase;

final class ChallengeDetectorTest extends TestCase
{
    public function test_detects_just_a_moment_title(): void
    {
        $html = '<html><head><title>Just a moment...</title></head><body>Wait</body></html>';

        self::assertTrue(ChallengeDetector::isChallenge($html, 200));
    }

    public function test_does_not_flag_full_page_with_turnstile_widget(): void
    {
        $html = str_repeat('<p>real content</p>', 2000) . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>';

        self::assertFalse(ChallengeDetector::isChallenge($html, 200));
        self::assertTrue(ChallengeDetector::isUsableBody($html, 200));
    }

    public function test_detects_javbus_age_verification_wall(): void
    {
        $html = '<html><head><title>Age Verification JavBus - JavBus</title></head><body><form id="driver-verify"></form></body></html>';

        self::assertTrue(ChallengeDetector::isChallenge($html, 200));
    }

    public function test_json_body_is_usable(): void
    {
        self::assertTrue(ChallengeDetector::isUsableBody('{"ok":true}', 200));
        self::assertFalse(ChallengeDetector::isUsableBody('', 200));
    }
}
