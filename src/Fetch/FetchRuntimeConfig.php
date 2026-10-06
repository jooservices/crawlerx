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
        public ?string $playwrightUrl = null,
        public ?string $userAgent = null,
        /** @var list<string> */
        public array $userAgentPool = [],
    ) {
    }

    public static function fromEnvironment(?string $packageRoot = null): self
    {
        $root = $packageRoot ?? dirname(__DIR__, 2);
        $playwright = getenv('CRAWLERX_PLAYWRIGHT_SCRIPT');
        $puppeteer = getenv('CRAWLERX_PUPPETEER_SCRIPT');
        $curl = getenv('CRAWLERX_CURL_IMPERSONATE');
        $flare = getenv('FLARESOLVERR_URL');
        $playwrightUrl = getenv('PLAYWRIGHT_URL');
        $userAgent = getenv('CRAWLERX_USER_AGENT');
        $userAgentPool = getenv('CRAWLERX_USER_AGENT_POOL');
        $defaultUserAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
        $configuredUserAgent = is_string($userAgent) && trim($userAgent) !== '' ? trim($userAgent) : $defaultUserAgent;
        $pool = is_string($userAgentPool) && trim($userAgentPool) !== ''
            ? array_values(array_filter(
                array_map('trim', explode(',', $userAgentPool)),
                static fn(string $value): bool => $value !== '',
            ))
            : [$configuredUserAgent];
        if (! in_array($configuredUserAgent, $pool, true)) {
            array_unshift($pool, $configuredUserAgent);
        }

        return new self(
            nodeBinary: 'node',
            playwrightScript: is_string($playwright) && $playwright !== ''
                ? $playwright
                : $root . '/scripts/playwright-fetch.mjs',
            puppeteerScript: is_string($puppeteer) && $puppeteer !== ''
                ? $puppeteer
                : $root . '/scripts/puppeteer-stealth-fetch.mjs',
            curlImpersonateBinary: is_string($curl) && $curl !== '' ? $curl : self::detectCurlImpersonate(),
            flaresolverrUrl: is_string($flare) && $flare !== '' ? $flare : null,
            playwrightUrl: is_string($playwrightUrl) && $playwrightUrl !== '' ? $playwrightUrl : null,
            userAgent: $configuredUserAgent,
            userAgentPool: $pool,
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
