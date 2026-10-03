<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Enums;

enum FetchMethod: string
{
    case Http = 'http';
    case CurlImpersonate = 'curl_impersonate';
    case Playwright = 'playwright';
    case PlaywrightStealth = 'playwright_stealth';
    case ChromeStealth = 'chrome_stealth';
    case PuppeteerStealth = 'puppeteer_stealth';
    case Flaresolverr = 'flaresolverr';

    /**
     * @return list<self>
     */
    public static function defaultChain(): array
    {
        $chain = [
            self::Http,
            self::Playwright,
            self::Flaresolverr,
        ];

        if (getenv('CRAWLERX_CURL_IMPERSONATE') !== false && trim((string) getenv('CRAWLERX_CURL_IMPERSONATE')) !== '') {
            array_splice($chain, 1, 0, [self::CurlImpersonate]);
        }

        return $chain;
    }

    /**
     * @return list<self>
     */
    public static function browserChain(): array
    {
        return [
            self::Playwright,
            self::Flaresolverr,
        ];
    }

    /**
     * @return list<self>
     */
    public static function httpOnlyChain(): array
    {
        return [self::Http];
    }
}
