<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use PHPUnit\Framework\TestCase;

final class JavBusAgeGateTest extends TestCase
{
    public function test_the_javbus_profile_sends_the_age_confirmation_cookie_on_every_fetch_method(): void
    {
        $manifest = (new FileAdapterManifestRegistry())->get('javbus');
        self::assertNotNull($manifest);

        $headers = SiteProfileDto::fromManifest($manifest)->http->headers;

        self::assertArrayHasKey('Cookie', $headers);
        self::assertStringContainsString('dv=1', $headers['Cookie']);
    }

    public function test_the_age_gate_page_is_still_reported_as_a_wall_when_the_cookie_is_missing(): void
    {
        $gate = '<html><head><title>Age Verification JavBus - JavBus</title></head><body><div id="ageVerify"></div></body></html>';

        self::assertTrue(ChallengeDetector::isChallenge($gate, 200));
    }
}
