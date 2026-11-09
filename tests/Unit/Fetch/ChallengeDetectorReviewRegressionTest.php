<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use PHPUnit\Framework\TestCase;

final class ChallengeDetectorReviewRegressionTest extends TestCase
{
    public function test_detects_long_captcha_walls_with_short_non_keyword_text_outside_the_form(): void
    {
        foreach (['Access denied', 'Request temporarily unavailable'] as $message) {
            $body = '<html><body><h1>' . $message . '</h1>'
                . '<form><div class="g-recaptcha" data-sitekey="example"></div></form>'
                . str_repeat(' ', 20000)
                . '</body></html>';

            self::assertGreaterThan(20000, strlen($body));
            self::assertTrue(ChallengeDetector::isCaptchaChallenge($body));
        }
    }

    public function test_does_not_classify_ordinary_prose_mentioning_captcha_as_a_challenge(): void
    {
        $body = '<html><body><article><p>CAPTCHA protects our comments.</p></article></body></html>';

        self::assertFalse(ChallengeDetector::isCaptchaChallenge($body));
    }
}
