<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use PHPUnit\Framework\TestCase;

final class ChallengeDetectorLongCaptchaTest extends TestCase
{
    public function test_detects_nested_captcha_form_in_a_long_page(): void
    {
        $html = '<html><body><form><div class="g-recaptcha" data-sitekey="example"></div></form>'
            . str_repeat(' ', 20000)
            . '</body></html>';

        self::assertGreaterThan(20000, strlen($html));
        self::assertTrue(ChallengeDetector::isCaptchaChallenge($html));
    }

    public function test_does_not_treat_a_recaptcha_script_as_a_challenge(): void
    {
        $html = '<html><head><script src="https://www.google.com/recaptcha/api.js"></script></head>'
            . '<body><article><h1>Gallery</h1><p>Article content remains available.</p></article></body></html>';

        self::assertFalse(ChallengeDetector::isCaptchaChallenge($html));
    }

    public function test_does_not_treat_a_captcha_widget_in_page_content_as_a_challenge(): void
    {
        $html = '<html><body><article><h1>Gallery</h1><p>Article content remains available.</p>'
            . '<form><div class="g-recaptcha" data-sitekey="example"></div></form></article></body></html>';

        self::assertFalse(ChallengeDetector::isCaptchaChallenge($html));
    }
}
