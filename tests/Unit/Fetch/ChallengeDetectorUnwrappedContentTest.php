<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use PHPUnit\Framework\TestCase;

final class ChallengeDetectorUnwrappedContentTest extends TestCase
{
    public function test_page_with_heading_and_paragraph_keeps_an_embedded_captcha_widget(): void
    {
        $body = '<html><body><h1>Gallery</h1><p>Article content remains available.</p>'
            . '<form><div class="g-recaptcha" data-sitekey="example"></div></form></body></html>';

        self::assertFalse(ChallengeDetector::isCaptchaChallenge($body));
    }
}
