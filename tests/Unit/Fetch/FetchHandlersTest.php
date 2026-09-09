<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\ProcessResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\CurlImpersonateFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PuppeteerStealthFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class FetchHandlersTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_curl_impersonate_reports_missing_binary(): void
    {
        $handler = new CurlImpersonateFetchHandler(
            new FetchRuntimeConfig(curlImpersonateBinary: null),
            $this->runner(new ProcessResultDto(0, 'unused')),
        );

        $result = $handler->fetch('https://example.test', $this->profile(), FetchMethod::CurlImpersonate);

        self::assertFalse($result->ok);
        self::assertStringContainsString('not found', (string) $result->error);
    }

    public function test_curl_impersonate_builds_request_with_options_and_cookies(): void
    {
        $runner = new class implements ProcessRunner {
            /** @var list<string> */
            public array $command = [];

            public int $timeout = 0;

            public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
            {
                $this->command = $command;
                $this->timeout = $timeoutSeconds;

                return new ProcessResultDto(0, '<html><body>usable curl response body</body></html>');
            }
        };
        $cookies = new CookieHandoffStore();
        $cookies->put('example.test', ['session' => 'token']);
        $handler = new CurlImpersonateFetchHandler(
            new FetchRuntimeConfig(curlImpersonateBinary: '/opt/curl-chrome'),
            $runner,
            $cookies,
        );

        $result = $handler->fetch(
            'https://example.test/path',
            $this->profile(),
            FetchMethod::CurlImpersonate,
            new CrawlOptionsDto(http: new HttpOptionsDto(timeout: 7)),
        );

        self::assertTrue($result->ok);
        self::assertSame(22, $runner->timeout);
        self::assertContains('session=token', $runner->command);
        self::assertContains('7', $runner->command);
    }

    public function test_curl_impersonate_rejects_challenge_output(): void
    {
        $handler = new CurlImpersonateFetchHandler(
            new FetchRuntimeConfig(curlImpersonateBinary: 'curl-chrome'),
            $this->runner(new ProcessResultDto(0, '<title>Just a moment...</title>')),
        );

        $result = $handler->fetch('https://example.test', $this->profile(), FetchMethod::CurlImpersonate);

        self::assertFalse($result->ok);
        self::assertTrue($result->challengeDetected);
        self::assertSame(403, $result->status);
    }

    public function test_puppeteer_parses_success_and_failure_results(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'crawlerx-puppeteer-');
        self::assertIsString($script);
        file_put_contents($script, '// test script');

        try {
            $handler = new PuppeteerStealthFetchHandler(
                new FetchRuntimeConfig(nodeBinary: 'node-test', puppeteerScript: $script),
                $this->runner(new ProcessResultDto(0, json_encode([
                    'status' => 200,
                    'html' => '<html><body>usable puppeteer response</body></html>',
                    'finalUrl' => 'https://example.test/final',
                    'cookies' => [['name' => 'clearance', 'value' => 'token']],
                ], JSON_THROW_ON_ERROR))),
            );

            $success = $handler->fetch('https://example.test', $this->profile(), FetchMethod::PuppeteerStealth);
            self::assertTrue($success->ok);
            self::assertSame('https://example.test/final', $success->finalUrl);
            self::assertSame('token', $success->cookies['clearance']);

            $invalid = new PuppeteerStealthFetchHandler(
                new FetchRuntimeConfig(puppeteerScript: $script),
                $this->runner(new ProcessResultDto(1, 'not-json', 'sidecar failed')),
            );
            self::assertSame(
                'sidecar failed',
                $invalid->fetch('https://example.test', $this->profile(), FetchMethod::PuppeteerStealth)->error,
            );
        } finally {
            unlink($script);
        }
    }

    public function test_puppeteer_reports_missing_script(): void
    {
        $handler = new PuppeteerStealthFetchHandler(
            new FetchRuntimeConfig(puppeteerScript: '/tmp/crawlerx-missing-puppeteer.mjs'),
            $this->runner(new ProcessResultDto(0, 'unused')),
        );

        self::assertFalse(
            $handler->fetch('https://example.test', $this->profile(), FetchMethod::PuppeteerStealth)->ok,
        );
    }

    public function test_flaresolverr_posts_json_and_parses_solution(): void
    {
        $client = new class implements ClientInterface {
            public ?RequestInterface $request = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, [], json_encode([
                    'status' => 'ok',
                    'solution' => [
                        'status' => 200,
                        'url' => 'https://example.test/final',
                        'response' => '<html><body>usable flare response</body></html>',
                    ],
                ], JSON_THROW_ON_ERROR));
            }
        };
        $handler = new FlaresolverrFetchHandler(
            new FetchRuntimeConfig(flaresolverrUrl: 'http://flare.test/v1'),
            $client,
        );

        $result = $handler->fetch('https://example.test', $this->profile(), FetchMethod::Flaresolverr);

        self::assertTrue($result->ok);
        self::assertSame('POST', $client->request?->getMethod());
        self::assertStringContainsString('request.get', (string) $client->request?->getBody());
        self::assertSame('https://example.test/final', $result->finalUrl);
    }

    public function test_flaresolverr_reports_configuration_transport_and_payload_errors(): void
    {
        $missing = new FlaresolverrFetchHandler(new FetchRuntimeConfig());
        self::assertFalse($missing->fetch('https://example.test', $this->profile(), FetchMethod::Flaresolverr)->ok);

        $throwing = $this->httpClient(static function (): never {
            throw new RuntimeException('offline');
        });
        $failed = new FlaresolverrFetchHandler(
            new FetchRuntimeConfig(flaresolverrUrl: 'http://flare.test/v1'),
            $throwing,
        );
        self::assertStringContainsString('offline', (string) $failed->fetch(
            'https://example.test',
            $this->profile(),
            FetchMethod::Flaresolverr,
        )->error);

        $invalid = new FlaresolverrFetchHandler(
            new FetchRuntimeConfig(flaresolverrUrl: 'http://flare.test/v1'),
            $this->httpClient(static fn(): ResponseInterface => new Response(200, [], 'invalid-json')),
        );
        self::assertStringContainsString('invalid JSON', (string) $invalid->fetch(
            'https://example.test',
            $this->profile(),
            FetchMethod::Flaresolverr,
        )->error);
    }

    public function test_http_handler_uses_client_fake_and_detects_challenges(): void
    {
        ClientBuilder::fake();
        ClientBuilder::respond('GET', 'https://example.test/ok', (new TestResponseSequence())->push(
            TestResponse::make(200, ['X-Test' => 'yes'], '<html><body>usable HTTP response body</body></html>'),
        ));
        ClientBuilder::respond('GET', 'https://example.test/wall', (new TestResponseSequence())->push(
            TestResponse::make(403, ['cf-mitigated' => 'challenge'], '<title>Just a moment...</title>'),
        ));
        $cookies = new CookieHandoffStore();
        $cookies->put('example.test', ['clearance' => 'ok']);
        $handler = new HttpFetchHandler(new ClientFactory(), $cookies);

        $success = $handler->fetch(
            'https://example.test/ok',
            $this->profile(),
            FetchMethod::Http,
            new CrawlOptionsDto(http: new HttpOptionsDto(headers: ['X-Override' => 'yes'])),
        );
        self::assertTrue($success->ok);
        self::assertSame(['yes'], $success->headers['X-Test']);

        $blocked = $handler->fetch('https://example.test/wall', $this->profile(), FetchMethod::Http);
        self::assertFalse($blocked->ok);
        self::assertTrue($blocked->challengeDetected);
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'test',
            displayName: 'Test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::BrowserLikely,
            fetchChain: FetchMethod::browserChain(),
            http: new HttpProfileDto(timeout: 30, headers: ['User-Agent' => 'CrawlerX-Test']),
        );
    }

    private function runner(ProcessResultDto $result): ProcessRunner
    {
        return new class ($result) implements ProcessRunner {
            public function __construct(private readonly ProcessResultDto $result)
            {
            }

            public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
            {
                return $this->result;
            }
        };
    }

    /** @param callable(RequestInterface): ResponseInterface $callback */
    private function httpClient(callable $callback): ClientInterface
    {
        return new class ($callback) implements ClientInterface {
            /** @param callable(RequestInterface): ResponseInterface $callback */
            public function __construct(private readonly mixed $callback)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                /** @var callable(RequestInterface): ResponseInterface $callback */
                $callback = $this->callback;

                return $callback($request);
            }
        };
    }
}
