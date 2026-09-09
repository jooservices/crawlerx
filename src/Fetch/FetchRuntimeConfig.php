<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

final readonly class FetchRuntimeConfig
{
    public function __construct(
        public string $nodeBinary = 'node',
        public string $playwrightScript = '',
        public string $puppeteerScript = '',
        public ?string $curlImpersonateBinary = null,
        public ?string $flaresolverrUrl = null,
        public ?string $browserServiceUrl = null,
    ) {
    }

    public static function fromEnvironment(?string $packageRoot = null): self
    {
        $root = $packageRoot ?? dirname(__DIR__, 2);
        $node = getenv('CRAWLERX_NODE');
        $playwright = getenv('CRAWLERX_PLAYWRIGHT_SCRIPT');
        $puppeteer = getenv('CRAWLERX_PUPPETEER_SCRIPT');
        $curl = getenv('CRAWLERX_CURL_IMPERSONATE');
        $flare = getenv('CRAWLERX_FLARESOLVERR_URL');
        $browserService = getenv('CRAWLERX_BROWSER_SERVICE_URL');

        return new self(
            nodeBinary: is_string($node) && $node !== '' ? $node : 'node',
            playwrightScript: is_string($playwright) && $playwright !== ''
                ? $playwright
                : $root . '/scripts/playwright-fetch.mjs',
            puppeteerScript: is_string($puppeteer) && $puppeteer !== ''
                ? $puppeteer
                : $root . '/scripts/puppeteer-stealth-fetch.mjs',
            curlImpersonateBinary: is_string($curl) && $curl !== '' ? $curl : self::detectCurlImpersonate(),
            flaresolverrUrl: is_string($flare) && $flare !== '' ? $flare : null,
            browserServiceUrl: is_string($browserService) && $browserService !== '' ? $browserService : null,
        );
    }

    private static function detectCurlImpersonate(): ?string
    {
        foreach (['curl-impersonate-chrome', 'curl_chrome131', 'curl-impersonate'] as $binary) {
            $path = trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }
}
