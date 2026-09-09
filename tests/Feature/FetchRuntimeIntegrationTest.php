<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\ProcessResultDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\CurlImpersonateFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PuppeteerStealthFetchHandler;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FetchRuntimeIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_browser_service_and_playwright_handler_crawl_through_facade(): void
    {
        $browserOutput = $this->browserOutput($this->loadFixture('onejav/listing-page-1.html'));
        $client = $this->client(static fn(): ResponseInterface => new Response(200, [], json_encode([
            'exitCode' => 0,
            'stdout' => $browserOutput,
            'stderr' => '',
        ], JSON_THROW_ON_ERROR)));
        $runtime = FetchRuntimeConfig::fromEnvironment();
        $handler = new PlaywrightFamilyFetchHandler(
            $runtime,
            new BrowserServiceProcessRunner('http://browser.test', $client),
        );

        $result = $this->crawlWith($handler, FetchMethod::Playwright);

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
    }

    public function test_puppeteer_handler_crawls_through_facade(): void
    {
        $runner = $this->runner(new ProcessResultDto(
            0,
            $this->browserOutput($this->loadFixture('onejav/listing-page-1.html')),
        ));
        $handler = new PuppeteerStealthFetchHandler(FetchRuntimeConfig::fromEnvironment(), $runner);

        self::assertNotEmpty($this->crawlWith($handler, FetchMethod::PuppeteerStealth)->items);
    }

    public function test_flaresolverr_handler_crawls_through_facade(): void
    {
        $body = $this->loadFixture('onejav/listing-page-1.html');
        $client = $this->client(static fn(): ResponseInterface => new Response(200, [], json_encode([
            'status' => 'ok',
            'solution' => [
                'status' => 200,
                'url' => 'https://onejav.com/new',
                'response' => $body,
                'cookies' => [['name' => 'clearance', 'value' => 'fixture-token']],
            ],
        ], JSON_THROW_ON_ERROR)));
        $handler = new FlaresolverrFetchHandler(
            new FetchRuntimeConfig(flaresolverrUrl: 'http://flare.test/v1'),
            $client,
        );

        self::assertNotEmpty($this->crawlWith($handler, FetchMethod::Flaresolverr)->items);
    }

    public function test_curl_impersonate_handler_crawls_through_facade(): void
    {
        $handler = new CurlImpersonateFetchHandler(
            new FetchRuntimeConfig(curlImpersonateBinary: '/opt/curl-chrome'),
            $this->runner(new ProcessResultDto(0, $this->loadFixture('onejav/listing-page-1.html'))),
        );

        self::assertNotEmpty($this->crawlWith($handler, FetchMethod::CurlImpersonate)->items);
    }

    public function test_proc_open_runner_executes_real_process_boundary(): void
    {
        $result = (new ProcOpenProcessRunner())->run([
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, "runtime-ok"); fwrite(STDERR, "runtime-note");',
        ]);

        self::assertSame(0, $result->exitCode);
        self::assertSame('runtime-ok', $result->stdout);
        self::assertSame('runtime-note', $result->stderr);
    }

    public function test_runtime_failures_map_to_blocked_outcomes(): void
    {
        $runtime = FetchRuntimeConfig::fromEnvironment();
        $cases = [
            [
                new PlaywrightFamilyFetchHandler(
                    $runtime,
                    $this->runner(new ProcessResultDto(1, 'invalid-json', 'playwright unavailable')),
                ),
                FetchMethod::Playwright,
            ],
            [
                new PuppeteerStealthFetchHandler(
                    $runtime,
                    $this->runner(new ProcessResultDto(0, json_encode([
                        'status' => 403,
                        'html' => '<title>Just a moment...</title>',
                        'challenge' => true,
                    ], JSON_THROW_ON_ERROR))),
                ),
                FetchMethod::PuppeteerStealth,
            ],
            [
                new FlaresolverrFetchHandler(
                    new FetchRuntimeConfig(flaresolverrUrl: 'http://flare.test/v1'),
                    $this->client(static fn(): ResponseInterface => new Response(200, [], 'invalid-json')),
                ),
                FetchMethod::Flaresolverr,
            ],
            [
                new CurlImpersonateFetchHandler(
                    new FetchRuntimeConfig(curlImpersonateBinary: null),
                    $this->runner(new ProcessResultDto(0, 'unused')),
                ),
                FetchMethod::CurlImpersonate,
            ],
        ];

        foreach ($cases as [$handler, $method]) {
            CrawlerXFactory::useFetchChain(new FetchFallbackChain([$method->value => $handler]));
            $outcome = CrawlerX::url('https://onejav.com/new')->options(new CrawlOptionsDto(
                fetch: new FetchOptionsDto(method: $method, noFallback: true),
            ))->tryCrawl();
            self::assertTrue($outcome->failed());
            self::assertSame(CrawlErrorCode::Blocked, $outcome->error?->code);
        }
    }

    public function test_http_challenge_maps_to_blocked_outcome(): void
    {
        ClientBuilder::fake();
        ClientBuilder::respond('GET', 'https://onejav.com/new', (new TestResponseSequence())->push(
            TestResponse::make(403, ['cf-mitigated' => 'challenge'], '<title>Just a moment...</title>'),
        ));
        $handler = new HttpFetchHandler(new ClientFactory());
        CrawlerXFactory::useFetchChain(new FetchFallbackChain([FetchMethod::Http->value => $handler]));

        $outcome = CrawlerX::url('https://onejav.com/new')->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertSame(CrawlErrorCode::Blocked, $outcome->error?->code);
    }

    public function test_browser_service_rejects_invalid_response(): void
    {
        $runner = new BrowserServiceProcessRunner(
            'http://browser.test',
            $this->client(static fn(): ResponseInterface => new Response(200, [], '{}')),
        );

        $config = tempnam(sys_get_temp_dir(), 'crawlerx-browser-feature-');
        self::assertIsString($config);
        file_put_contents($config, json_encode(['url' => 'https://example.test'], JSON_THROW_ON_ERROR));

        try {
            $result = $runner->run(['node', '/app/scripts/playwright-fetch.mjs', '--config=' . $config]);
            self::assertSame(1, $result->exitCode);
            self::assertSame('', $result->stdout);
        } finally {
            unlink($config);
        }
    }

    public function test_proc_open_runner_enforces_timeout(): void
    {
        $result = (new ProcOpenProcessRunner())->run([
            PHP_BINARY,
            '-r',
            'usleep(1500000);',
        ], 1);

        self::assertSame(124, $result->exitCode);
        self::assertStringContainsString('timed out', $result->stderr);
    }

    private function crawlWith(FetchMethodHandler $handler, FetchMethod $method): CrawlListResultDto
    {
        CrawlerXFactory::useFetchChain(new FetchFallbackChain([$method->value => $handler]));

        $result = CrawlerX::url('https://onejav.com/new')->options(new CrawlOptionsDto(
            fetch: new FetchOptionsDto(method: $method, noFallback: true),
        ))->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $result);

        return $result;
    }

    private function browserOutput(string $html): string
    {
        return json_encode([
            'status' => 200,
            'html' => $html,
            'finalUrl' => 'https://onejav.com/new',
            'elapsedMs' => 2,
            'challenge' => false,
            'cookies' => [['name' => 'session', 'value' => 'fixture-token']],
        ], JSON_THROW_ON_ERROR);
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

    private function client(callable $callback): ClientInterface
    {
        return new class ($callback) implements ClientInterface {
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
