<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Http\MapCrawlHttpClient;
use JOOservices\CrawlerX\Http\SeededCrawlHttpClient;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class FetchInfrastructureTest extends TestCase
{
    public function test_cookie_handoff_normalizes_hosts_and_forgets_values(): void
    {
        $store = new CookieHandoffStore();
        self::assertNull($store->cookieHeader('example.test'));

        $store->put('HTTPS://Example.Test/path', ['a' => '1', 'b' => '2']);
        self::assertSame(['a' => '1', 'b' => '2'], $store->get('example.test'));
        self::assertSame('a=1; b=2', $store->cookieHeader('https://example.test/other'));

        $store->put('example.test', []);
        $store->forget('EXAMPLE.TEST');
        self::assertSame([], $store->get('example.test'));
    }

    public function test_seeded_client_serves_fetch_and_delegates_other_urls(): void
    {
        $inner = new MapCrawlHttpClient(['https://example.test/secondary' => 'secondary body']);
        $client = new SeededCrawlHttpClient($inner, new FetchResultDto(
            ok: true,
            body: '{"items":[]}',
            status: 200,
            methodUsed: FetchMethod::Playwright,
            elapsedMs: 1,
            challengeDetected: false,
            finalUrl: 'https://example.test/main/',
            headers: ['X-Seed' => ['yes']],
        ));

        $seeded = $client->get('https://example.test/main')->toPsrResponse();
        self::assertSame('application/json', $seeded->getHeaderLine('Content-Type'));
        self::assertSame('yes', $seeded->getHeaderLine('X-Seed'));
        self::assertSame('{"items":[]}', (string) $seeded->getBody());
        self::assertSame('secondary body', (string) $client->get('https://example.test/secondary')->toPsrResponse()->getBody());
    }

    public function test_process_runner_captures_output_exit_code_and_timeout(): void
    {
        $runner = new ProcOpenProcessRunner();
        $result = $runner->run([PHP_BINARY, '-r', 'fwrite(STDOUT, "out"); fwrite(STDERR, "err");']);
        self::assertSame(0, $result->exitCode);
        self::assertSame('out', $result->stdout);
        self::assertSame('err', $result->stderr);

        $timeout = $runner->run([PHP_BINARY, '-r', 'sleep(2);'], 0);
        self::assertSame(124, $timeout->exitCode);
        self::assertStringContainsString('timed out', $timeout->stderr);
    }

    public function test_process_runner_rejects_empty_command(): void
    {
        $this->expectException(RuntimeException::class);
        (new ProcOpenProcessRunner())->run([]);
    }

    public function test_runtime_config_uses_package_paths_and_environment_overrides(): void
    {
        $variables = [
            'CRAWLERX_NODE' => 'node-custom',
            'CRAWLERX_PLAYWRIGHT_SCRIPT' => '/tmp/playwright-custom.mjs',
            'CRAWLERX_PUPPETEER_SCRIPT' => '/tmp/puppeteer-custom.mjs',
            'CRAWLERX_CURL_IMPERSONATE' => '/tmp/curl-custom',
            'CRAWLERX_FLARESOLVERR_URL' => 'http://flare.test/v1',
            'CRAWLERX_BROWSER_SERVICE_URL' => 'http://browser.test:3000',
        ];

        try {
            foreach ($variables as $name => $value) {
                putenv($name . '=' . $value);
            }
            $config = FetchRuntimeConfig::fromEnvironment('/package');
            self::assertSame('node-custom', $config->nodeBinary);
            self::assertSame('/tmp/playwright-custom.mjs', $config->playwrightScript);
            self::assertSame('/tmp/puppeteer-custom.mjs', $config->puppeteerScript);
            self::assertSame('/tmp/curl-custom', $config->curlImpersonateBinary);
            self::assertSame('http://flare.test/v1', $config->flaresolverrUrl);
            self::assertSame('http://browser.test:3000', $config->browserServiceUrl);
        } finally {
            foreach (array_keys($variables) as $name) {
                putenv($name);
            }
        }

        $defaults = FetchRuntimeConfig::fromEnvironment('/package');
        self::assertSame('/package/scripts/playwright-fetch.mjs', $defaults->playwrightScript);
        self::assertSame('/package/scripts/puppeteer-stealth-fetch.mjs', $defaults->puppeteerScript);
    }

    public function test_browser_service_runner_forwards_config_and_decodes_result(): void
    {
        $config = tempnam(sys_get_temp_dir(), 'crawlerx-browser-config-');
        self::assertIsString($config);
        file_put_contents($config, '{"url":"https://example.test","headless":true}');
        $client = new class implements ClientInterface {
            public ?RequestInterface $request = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, [], json_encode([
                    'exitCode' => 0,
                    'stdout' => '{"status":200}',
                    'stderr' => '',
                ], JSON_THROW_ON_ERROR));
            }
        };

        try {
            $result = (new BrowserServiceProcessRunner('http://browser.test:3000/', $client))->run([
                'node',
                '/app/scripts/puppeteer-stealth-fetch.mjs',
                '--config=' . $config,
            ]);
        } finally {
            unlink($config);
        }

        self::assertSame(0, $result->exitCode);
        self::assertSame('{"status":200}', $result->stdout);
        self::assertStringContainsString('/fetch', (string) $client->request?->getUri());
        self::assertStringContainsString('puppeteer', (string) $client->request?->getBody());
    }

    public function test_browser_service_runner_rejects_missing_config_argument(): void
    {
        $this->expectException(RuntimeException::class);
        (new BrowserServiceProcessRunner('http://browser.test'))->run(['node', 'script.mjs']);
    }
}
