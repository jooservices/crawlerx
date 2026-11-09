<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Fetch\Cache\ResponseCache;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\Support\LocalHttpServer;
use JOOservices\CrawlerX\Tests\Support\ExpiringArrayCache;
use PHPUnit\Framework\TestCase;

final class HttpRedirectSecurityTest extends TestCase
{
    public function test_cache_does_not_store_a_response_personalized_by_a_redirect_cookie(): void
    {
        $requestLog = sys_get_temp_dir() . '/crawlerx-redirect-cache-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->cacheRedirectRouter($requestLog));

        try {
            $cache = new ExpiringArrayCache(static fn(): float => 1.0);
            $handler = new ResponseCache(
                new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore(), maxResponseBytes: 4096),
                $cache,
                60,
            );
            $url = $server->url('/start');
            $profile = $this->profile();

            $first = $handler->fetch($url, $profile, FetchMethod::Http);
            $second = $handler->fetch($url, $profile, FetchMethod::Http);

            self::assertSame($server->url('/private'), $first->finalUrl);
            self::assertSame('<html><body>personalized alice response</body></html>', $first->body);
            self::assertSame($first->body, $second->body);
            self::assertSame([
                '/start|',
                '/private|session=alice',
                '/start|',
                '/private|session=alice',
            ], $this->requestLog($requestLog));
        } finally {
            $server->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    public function test_robots_is_checked_before_each_redirect_destination_is_requested(): void
    {
        $requestLog = sys_get_temp_dir() . '/crawlerx-robots-redirect-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->robotsRedirectRouter($requestLog));
        $robots = new RobotsGuard(static fn(string $url): ?string => "User-agent: *\nDisallow: /private\n");
        $http = new HttpFetchHandler(
            new ClientFactory(),
            new CookieHandoffStore(),
            maxResponseBytes: 4096,
            robotsGuard: $robots,
        );
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $http],
            robotsGuard: $robots,
        );

        try {
            try {
                $chain->fetch($server->url('/start'), $this->profile(), [FetchMethod::Http]);
                self::fail('The disallowed redirect destination must be rejected.');
            } catch (RobotsDisallowedException $exception) {
                self::assertStringContainsString('/private', $exception->getMessage());
            }

            self::assertSame(['/start'], $this->requestLog($requestLog));
        } finally {
            $server->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    public function test_configured_body_limit_stops_a_chunked_download_during_transport(): void
    {
        $sentBytesLog = sys_get_temp_dir() . '/crawlerx-body-limit-' . bin2hex(random_bytes(8));
        $maxResponseBytes = 8192;
        $server = LocalHttpServer::start($this->chunkedResponseRouter($sentBytesLog));

        try {
            $handler = new HttpFetchHandler(
                new ClientFactory(),
                new CookieHandoffStore(),
                maxResponseBytes: $maxResponseBytes,
            );

            $result = $handler->fetch($server->url('/large'), $this->profile(), FetchMethod::Http);
            self::assertFalse($result->ok);
            self::assertSame('response_too_large', $result->error);

            $sentBytes = $this->waitForIntegerFile($sentBytesLog);
            self::assertGreaterThanOrEqual($maxResponseBytes, $sentBytes);
            self::assertLessThanOrEqual($maxResponseBytes + 65_536, $sentBytes);
        } finally {
            $server->stop();
            if (is_file($sentBytesLog)) {
                unlink($sentBytesLog);
            }
        }
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'http-security-test',
            displayName: 'HTTP security test',
            baseUrl: 'http://127.0.0.1',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: ['Accept' => 'text/html', 'User-Agent' => 'CrawlerX security test']),
        );
    }

    private function cacheRedirectRouter(string $requestLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $requestLog = %s;
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            file_put_contents($requestLog, $path . '|' . ($_SERVER['HTTP_COOKIE'] ?? '') . PHP_EOL, FILE_APPEND | LOCK_EX);

            if ($path === '/start') {
                header('Set-Cookie: session=alice; Path=/; HttpOnly');
                header('Location: /private', true, 302);
                exit;
            }

            header('Content-Type: text/html; charset=utf-8');
            echo ($_COOKIE['session'] ?? '') === 'alice'
                ? '<html><body>personalized alice response</body></html>'
                : '<html><body>anonymous response body</body></html>';
            PHP,
            var_export($requestLog, true),
        );
    }

    private function robotsRedirectRouter(string $requestLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $requestLog = %s;
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            file_put_contents($requestLog, $path . PHP_EOL, FILE_APPEND | LOCK_EX);

            if ($path === '/start') {
                header('Location: /private', true, 302);
                exit;
            }

            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body>private redirect response</body></html>';
            PHP,
            var_export($requestLog, true),
        );
    }

    private function chunkedResponseRouter(string $sentBytesLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $sentBytesLog = %s;
            ignore_user_abort(true);
            header('Content-Type: text/plain');
            $chunk = str_repeat('x', 1024);
            $sent = 0;
            while ($sent < 2_000_000) {
                echo $chunk;
                flush();
                $sent += strlen($chunk);
                if (connection_aborted()) {
                    break;
                }
                usleep(1_000);
            }
            file_put_contents($sentBytesLog, (string) $sent);
            PHP,
            var_export($sentBytesLog, true),
        );
    }

    /** @return list<string> */
    private function requestLog(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        return $lines;
    }

    private function waitForIntegerFile(string $path): int
    {
        $deadline = microtime(true) + 3.0;
        do {
            clearstatcache(true, $path);
            if (is_file($path)) {
                return (int) file_get_contents($path);
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail('The chunked response server did not record its sent byte count.');
    }
}
