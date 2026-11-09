<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Support\ChallengeResponseDetector;
use Symfony\Component\DomCrawler\Crawler;

final class ChallengeDetector
{
    /**
     * @param  array<string, list<string>|string>  $headers
     */
    public static function isChallenge(string $body, int $status, array $headers = []): bool
    {
        return ChallengeResponseDetector::isChallenge($body, $status, $headers);
    }

    public static function isCaptchaChallenge(string $body): bool
    {
        if (self::hasCaptchaForm($body)) {
            return true;
        }

        $visibleText = trim(self::visibleText($body));
        if (self::isStandaloneCaptchaMessage($visibleText)) {
            return true;
        }

        return preg_match('/<script\b[^>]*\bsrc=["\'][^"\']*(?:captcha|recaptcha|hcaptcha|funcaptcha)[^"\']*["\']/i', $body) === 1
            && preg_match('/\b(?:verify|verification|human|robot|security check)\b/i', $visibleText) === 1;
    }

    /** @param list<string> $readyMarkers */
    public static function isUsableBody(string $body, int $status, array $readyMarkers = []): bool
    {
        if ($status >= 400) {
            return false;
        }

        $trimmed = trim($body);
        if ($trimmed === '') {
            return false;
        }

        $isJson = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
        if ($isJson) {
            return json_decode($trimmed) !== null;
        }

        if ($readyMarkers !== [] && $status >= 200 && $status < 300) {
            foreach ($readyMarkers as $marker) {
                if (self::bodyContainsMarker($body, $marker)) {
                    return true;
                }
            }

            return false;
        }

        return strlen($trimmed) >= 32;
    }

    public static function bodyContainsMarker(string $body, string $marker): bool
    {
        $selectors = preg_split('/\s*,\s*/', $marker);
        if ($selectors === false) {
            return false;
        }

        foreach ($selectors as $selector) {
            if (self::bodyContainsSelector($body, trim($selector))) {
                return true;
            }
        }

        return false;
    }

    private static function bodyContainsSelector(string $body, string $selector): bool
    {
        if ($selector === '') {
            return false;
        }

        try {
            return (new Crawler($body))->filter($selector)->count() > 0;
        } catch (\Throwable) {
            // Text markers are handled by TerminalStatus against visible text.
            // A raw-body fallback would also match content inside scripts.
            return false;
        }
    }

    private static function hasCaptchaForm(string $body): bool
    {
        if (
            stripos($body, '<form') === false
            || (stripos($body, 'captcha') === false && stripos($body, 'data-sitekey') === false)
        ) {
            return false;
        }

        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $captchaAttribute = sprintf(
            'contains(translate(concat(@class, " ", @id, " ", @name, " ", @action, " ", @src), "%s", "%s"), "captcha")',
            $letters,
            $lowercase,
        );
        $formAttribute = sprintf(
            'contains(translate(concat(@class, " ", @id, " ", @name, " ", @action), "%s", "%s"), "captcha")',
            $letters,
            $lowercase,
        );

        $captchaForms = (new Crawler($body))->filterXPath(
            sprintf('//form[%s or .//*[%s or @data-sitekey]]', $formAttribute, $captchaAttribute),
        );
        if ($captchaForms->count() === 0) {
            return false;
        }

        $bodyWithoutForms = preg_replace('/<form\b[^>]*>.*?<\/form\s*>/is', '', $body) ?? $body;
        $outsideText = trim(self::visibleText($bodyWithoutForms));

        return $outsideText === ''
            || preg_match('/\b(?:captcha|challenge|robot|human)\b|verify you are human|security check/i', $outsideText) === 1
            || (strlen($outsideText) <= 80 && ! self::hasSubstantiveContentRegion($bodyWithoutForms));
    }

    private static function hasSubstantiveContentRegion(string $body): bool
    {
        $regions = (new Crawler($body))->filterXPath('//article|//main|//section');
        foreach ($regions as $region) {
            if (strlen(trim((new Crawler($region))->text(''))) > 20) {
                return true;
            }
        }

        return false;
    }

    private static function isStandaloneCaptchaMessage(string $visibleText): bool
    {
        return preg_match(
            '/^(?:captcha|recaptcha|hcaptcha|funcaptcha)(?:\s+(?:challenge|page|verification(?:\s+(?:is\s+)?required)?|is required))?[.!]?$/i',
            $visibleText,
        ) === 1;
    }

    private static function visibleText(string $body): string
    {
        $withoutExecutableText = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $body);

        return html_entity_decode(strip_tags($withoutExecutableText ?? $body), ENT_QUOTES | ENT_HTML5);
    }
}
