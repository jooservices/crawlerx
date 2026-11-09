<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Support\CrawlerUserAgent;

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
        public string $nodeId = 'local',
        public bool $respectRobots = true,
        public int $responseCacheTtlSeconds = 0,
        public int $maxResponseBytes = 10_485_760,
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
        $userAgentPool = getenv('CRAWLERX_USER_AGENT_POOL');
        $nodeId = getenv('CRAWLERX_NODE');
        $responseCacheTtl = getenv('CRAWLERX_RESPONSE_CACHE_TTL');
        $maxResponseBytes = getenv('CRAWLERX_MAX_RESPONSE_BYTES');
        $configuredUserAgent = CrawlerUserAgent::fromEnvironment();
        $pool = is_string($userAgentPool) && trim($userAgentPool) !== ''
            ? array_values(array_filter(
                array_map('trim', explode(',', $userAgentPool)),
                static fn(string $value): bool => $value !== '',
            ))
            : [$configuredUserAgent];
        if (! in_array($configuredUserAgent, $pool, true)) {
            array_unshift($pool, $configuredUserAgent);
        }

        $resolvedNodeId = is_string($nodeId) ? trim($nodeId) : '';
        if ($resolvedNodeId === '') {
            $hostname = gethostname();
            $resolvedNodeId = is_string($hostname) && $hostname !== '' ? $hostname : 'local';
        }

        $responseCacheTtlSeconds = is_string($responseCacheTtl) && ctype_digit(trim($responseCacheTtl))
            ? (int) trim($responseCacheTtl)
            : 0;
        $configuredMaxResponseBytes = is_string($maxResponseBytes) && ctype_digit(trim($maxResponseBytes))
            ? filter_var(trim($maxResponseBytes), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 104_857_600]])
            : false;

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
            nodeId: $resolvedNodeId,
            respectRobots: self::resolveRespectRobots(getenv('CRAWLERX_RESPECT_ROBOTS')),
            responseCacheTtlSeconds: $responseCacheTtlSeconds,
            maxResponseBytes: is_int($configuredMaxResponseBytes) ? $configuredMaxResponseBytes : 10_485_760,
        );
    }

    private static function resolveRespectRobots(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return true;
        }

        return ! in_array(strtolower(trim($value)), ['0', 'false', 'no', 'off'], true);
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
