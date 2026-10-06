<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Session;

use Faker\Factory;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use PHPUnit\Framework\TestCase;

final class FetchFallbackSessionTest extends TestCase
{
    public function test_tc_se_01_flare_session_is_replayed_by_five_http_requests(): void
    {
        $http = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            $cookie = $profile->http->headers['Cookie'] ?? '';
            $userAgent = $profile->http->headers['User-Agent'] ?? '';
            $ok = str_contains($cookie, 'cf_clearance=flare-token') && $userAgent === 'Flare-UA';

            return $this->makeResult($method, $url, $ok, $ok ? '<html><body>authenticated fixture response</body></html>' : '<title>Just a moment...</title>');
        });
        $flareCalls = 0;
        $flare = new RecordingHandler(FetchMethod::Flaresolverr, function (SiteProfileDto $profile, FetchMethod $method, string $url) use (&$flareCalls): FetchResultDto {
            ++$flareCalls;

            return new FetchResultDto(
                ok: true,
                body: '<html><body>flare solved fixture response</body></html>',
                status: 200,
                methodUsed: $method,
                elapsedMs: 1,
                challengeDetected: false,
                finalUrl: $url,
                cookies: ['cf_clearance' => 'flare-token'],
                userAgent: 'Flare-UA',
            );
        });
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $http, FetchMethod::Flaresolverr->value => $flare],
            sessions: new SessionStore(),
            runtime: new FetchRuntimeConfig(userAgent: 'Base-UA', userAgentPool: ['Base-UA']),
        );

        $first = $chain->fetch('https://fixture.test/cf-bound/1', $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr]);
        self::assertSame(FetchMethod::Flaresolverr, $first->methodUsed);
        for ($index = 0; $index < 5; ++$index) {
            self::assertTrue($chain->fetch('https://fixture.test/cf-bound/' . ($index + 2), $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr])->ok);
        }

        self::assertSame(1, $flareCalls);
        self::assertCount(6, $http->seen);
        self::assertSame('Flare-UA', $http->seen[1]['userAgent']);
    }

    public function test_tc_se_02_wrong_replayed_ua_forgets_the_session_and_resolves_again(): void
    {
        $store = new SessionStore();
        $store->put('fixture', ['cf_clearance' => 'old-token'], 'Old-UA', 'flaresolverr');
        $http = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            $valid = ($profile->http->headers['Cookie'] ?? '') === 'cf_clearance=new-token'
                && ($profile->http->headers['User-Agent'] ?? '') === 'New-UA';

            return $this->makeResult($method, $url, $valid, '<title>Just a moment...</title>');
        });
        $flare = new RecordingHandler(FetchMethod::Flaresolverr, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            return new FetchResultDto(
                ok: true,
                body: '<html><body>flare solved fixture response</body></html>',
                status: 200,
                methodUsed: $method,
                elapsedMs: 1,
                challengeDetected: false,
                finalUrl: $url,
                cookies: ['cf_clearance' => 'new-token'],
                userAgent: 'New-UA',
            );
        });
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $http, FetchMethod::Flaresolverr->value => $flare],
            sessions: $store,
            runtime: new FetchRuntimeConfig(userAgent: 'New-UA', userAgentPool: ['New-UA']),
        );

        self::assertTrue($chain->fetch('https://fixture.test/cf-bound/1', $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr])->ok);
        self::assertSame('new-token', $store->get('fixture')['cookies']['cf_clearance']);
        self::assertSame('New-UA', $store->userAgent('fixture'));
        self::assertSame('cf_clearance=old-token', $http->seen[0]['cookies']);
    }

    public function test_tc_se_06_login_cookie_provider_authenticates_a_request(): void
    {
        $faker = Factory::create();
        $secret = $faker->sha256();
        $provider = new class ($secret) implements LoginCookieProvider {
            public function __construct(private readonly string $secret)
            {
            }

            public function cookiesFor(string $site): array
            {
                return ['remember_token' => $this->secret];
            }
        };
        $handler = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url) use ($secret): FetchResultDto {
            $ok = ($profile->http->headers['Cookie'] ?? '') === 'remember_token=' . $secret;

            return $this->makeResult($method, $url, $ok, $ok ? '<html><body>authenticated fixture response</body></html>' : '<html><body>login required</body></html>', $ok ? 200 : 401);
        });
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $handler],
            logins: $provider,
        );

        self::assertTrue($chain->fetch('https://fixture.test/login-wall/1', $this->profile(), [FetchMethod::Http])->ok);
    }

    public function test_tc_se_07_login_wall_without_cookie_is_auth_required_and_not_retryable(): void
    {
        $handler = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            return $this->makeResult($method, $url, false, '<html><body>login required</body></html>', 401);
        });
        $chain = new FetchFallbackChain([FetchMethod::Http->value => $handler]);

        try {
            $chain->fetch('https://fixture.test/login-wall/1', $this->profile(), [FetchMethod::Http]);
            self::fail('Expected auth_required.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::AuthRequired, $exception->errorCode);
            self::assertFalse($exception->retryable);
        }
    }

    public function test_storage_state_is_passed_to_the_browser_step(): void
    {
        $store = new SessionStore();
        $store->put('fixture', [], 'Fixture-UA', 'playwright', null, [
            'cookies' => [['name' => 'session', 'value' => 'value']],
            'origins' => [],
        ]);
        $handler = new RecordingHandler(FetchMethod::Playwright, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            return new FetchResultDto(true, '<html><body>browser response body</body></html>', 200, $method, 1, false, $url);
        });
        $handler->inspectOptions = true;
        $chain = new FetchFallbackChain([FetchMethod::Playwright->value => $handler], sessions: $store);

        self::assertTrue($chain->fetch('https://fixture.test/page', $this->profile(), [FetchMethod::Playwright])->ok);
        self::assertSame('value', $handler->options[0]->storageState['cookies'][0]['value']);
        self::assertSame('Fixture-UA', $handler->seen[0]['userAgent']);
    }

    public function test_http_redirect_cookie_is_replayed_only_within_its_request(): void
    {
        $faker = Factory::create();
        $sessionCookie = $faker->sha256();
        $redirectCookie = $faker->sha256();
        $readinessToken = bin2hex(random_bytes(16));
        $directory = sys_get_temp_dir() . '/crawlerx-redirect-cookie-' . bin2hex(random_bytes(6));
        $router = $directory . '/router.php';
        $requestLog = $directory . '/requests.jsonl';
        $server = null;
        $directoryCreated = false;
        try {
            self::assertTrue(mkdir($directory));
            $directoryCreated = true;
            $routerContents = sprintf(
                <<<'PHP'
                <?php
                $requestLog = %s;
                $readinessToken = %s;
                if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/__crawlerx_ready'
                    && ($_GET['token'] ?? '') === $readinessToken) {
                    header('Content-Type: text/plain');
                    echo $readinessToken;
                    exit;
                }

                file_put_contents($requestLog, json_encode([
                    'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
                    'cookie' => $_SERVER['HTTP_COOKIE'] ?? '',
                ], JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);

                if (($_SERVER['REQUEST_URI'] ?? '/') === '/redirect') {
                    header('Set-Cookie: redirect_only=%s; Path=/; HttpOnly');
                    header('Location: /final', true, 302);
                    exit;
                }

                header('Content-Type: text/html; charset=utf-8');
                echo '<html><body>usable redirect-cookie response</body></html>';
                PHP,
                var_export($requestLog, true),
                var_export($readinessToken, true),
                $redirectCookie,
            );
            self::assertNotFalse(file_put_contents($router, $routerContents));

            $server = $this->startServer($router, $directory, $readinessToken);
            $port = $server['port'];
            $sessions = new SessionStore();
            $sessions->put('fixture', ['session_cookie' => $sessionCookie], 'Session-UA', 'manual');
            $cookieHandoff = new CookieHandoffStore();
            $http = new HttpFetchHandler(new ClientFactory(), $cookieHandoff, $sessions);
            $chain = new FetchFallbackChain(
                [FetchMethod::Http->value => $http],
                $cookieHandoff,
                $sessions,
            );
            $profile = $this->profile(cookieHandoffAfterBrowser: true);

            $first = $chain->fetch('http://127.0.0.1:' . $port . '/redirect', $profile, [FetchMethod::Http]);
            $second = $chain->fetch('http://127.0.0.1:' . $port . '/second', $profile, [FetchMethod::Http]);

            self::assertTrue($first->ok);
            self::assertTrue($second->ok);
            $lines = file($requestLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $requests = array_map(static function (string $line): array {
                $request = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($request);

                return $request;
            }, $lines);

            self::assertCount(3, $requests);
            self::assertSame('/redirect', $requests[0]['path']);
            self::assertSame('session_cookie=' . $sessionCookie, $requests[0]['cookie']);
            self::assertSame('/final', $requests[1]['path']);
            self::assertStringContainsString('session_cookie=' . $sessionCookie, $requests[1]['cookie']);
            self::assertStringContainsString('redirect_only=' . $redirectCookie, $requests[1]['cookie']);
            self::assertSame('/second', $requests[2]['path']);
            self::assertSame('session_cookie=' . $sessionCookie, $requests[2]['cookie']);
            self::assertSame(['session_cookie' => $sessionCookie], $sessions->get('fixture')['cookies']);
            self::assertSame([], $cookieHandoff->get('127.0.0.1'));
        } finally {
            if ($server !== null) {
                proc_terminate($server['process']);
                foreach ([$server['pipes'][1], $server['pipes'][2]] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($server['process']);
            }
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
            if (is_file($router)) {
                unlink($router);
            }
            if ($directoryCreated && is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function profile(bool $cookieHandoffAfterBrowser = false): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'fixture',
            displayName: 'Fixture',
            baseUrl: 'https://fixture.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http, FetchMethod::Flaresolverr],
            http: new HttpProfileDto(),
            playwright: new PlaywrightProfileDto(),
            cookieHandoffAfterBrowser: $cookieHandoffAfterBrowser,
        );
    }

    private function makeResult(FetchMethod $method, string $url, bool $ok, string $body, int $status = 403): FetchResultDto
    {
        return new FetchResultDto($ok, $body, $ok ? 200 : $status, $method, 1, ! $ok, $url, error: $ok ? null : 'challenge');
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($socket, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::assertIsString($address);

        return (int) substr($address, (int) strrpos($address, ':') + 1);
    }

    /**
     * @return array{process: resource, pipes: array<int, mixed>, port: int}
     */
    private function startServer(string $router, string $directory, string $readinessToken): array
    {
        $lastExitCode = -1;
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $port = $this->freePort();
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $directory,
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $ready = $this->waitForEndpoint($port, $readinessToken, $process);
            $status = proc_get_status($process);
            if ($ready && $status['running']) {
                return ['process' => $process, 'pipes' => $pipes, 'port' => $port];
            }

            $lastExitCode = $status['exitcode'];
            proc_terminate($process);
            foreach ([$pipes[1], $pipes[2]] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }

        self::fail(sprintf('Local redirect-cookie endpoint did not start after 3 attempts (last exit code: %d).', $lastExitCode));
    }

    /**
     * @param resource $process
     */
    private function waitForEndpoint(int $port, string $readinessToken, $process): bool
    {
        $deadline = microtime(true) + 3.0;
        do {
            $status = proc_get_status($process);
            if (! $status['running']) {
                return false;
            }

            $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                stream_set_timeout($socket, 0, 100_000);
                $request = sprintf(
                    "GET /__crawlerx_ready?token=%s HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n",
                    rawurlencode($readinessToken),
                );
                fwrite($socket, $request);
                $response = stream_get_contents($socket);
                fclose($socket);

                if (is_string($response) && preg_match('/\AHTTP\/\d(?:\.\d)? 200\b/', $response) === 1) {
                    $parts = explode("\r\n\r\n", $response, 2);
                    if (isset($parts[1]) && hash_equals($readinessToken, trim($parts[1]))) {
                        return true;
                    }
                }
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        return false;
    }
}

final class RecordingHandler implements FetchMethodHandler
{
    /** @var list<array{cookies: string, userAgent: string}> */
    public array $seen = [];

    /** @var list<CrawlOptionsDto> */
    public array $options = [];

    public bool $inspectOptions = false;

    public function __construct(
        private readonly FetchMethod $method,
        private readonly \Closure $callback,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === $this->method;
    }

    public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
    {
        $this->seen[] = [
            'cookies' => $profile->http->headers['Cookie'] ?? '',
            'userAgent' => $profile->http->headers['User-Agent'] ?? '',
        ];
        if ($this->inspectOptions && $options !== null) {
            $this->options[] = $options;
        }

        return ($this->callback)($profile, $method, $url, $options);
    }
}
