<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\CurlImpersonateFetchHandler;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Tests\Support\LocalHttpServer;
use PHPUnit\Framework\TestCase;

final class CurlImpersonateRedirectRegressionTest extends TestCase
{
    public function test_curl_follows_same_origin_redirects_when_robots_are_opted_out(): void
    {
        $this->requireCurlBinary();
        $requestLog = sys_get_temp_dir() . '/crawlerx-curl-same-origin-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->sameOriginRedirectRouter($requestLog));

        try {
            $result = $this->handler(4096)->fetch($server->url('/start'), $this->profile(), FetchMethod::CurlImpersonate);

            self::assertTrue($result->ok);
            self::assertSame($server->url('/done'), $result->finalUrl);
            self::assertSame(['/start', '/done'], $this->requestLog($requestLog));
        } finally {
            $server->stop();
            $this->removeLog($requestLog);
        }
    }

    public function test_curl_rejects_cross_origin_redirects_before_requesting_the_destination(): void
    {
        $this->requireCurlBinary();
        $requestLog = sys_get_temp_dir() . '/crawlerx-curl-cross-origin-' . bin2hex(random_bytes(8));
        $destinationLog = sys_get_temp_dir() . '/crawlerx-curl-destination-' . bin2hex(random_bytes(8));
        $destination = LocalHttpServer::start($this->destinationRouter($destinationLog));
        $source = LocalHttpServer::start($this->crossOriginRedirectRouter($requestLog, $destination->url('/private')));

        try {
            $result = $this->handler(4096)->fetch($source->url('/start'), $this->profile(), FetchMethod::CurlImpersonate);

            self::assertFalse($result->ok);
            self::assertSame('redirect changed origin', $result->error);
            self::assertSame(['/start'], $this->requestLog($requestLog));
            self::assertFileDoesNotExist($destinationLog);
        } finally {
            $source->stop();
            $destination->stop();
            $this->removeLog($requestLog);
            $this->removeLog($destinationLog);
        }
    }

    public function test_curl_keeps_cookies_set_by_same_origin_redirects(): void
    {
        $this->requireCurlBinary();
        $requestLog = sys_get_temp_dir() . '/crawlerx-curl-redirect-cookie-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->redirectCookieRouter($requestLog));

        try {
            $result = $this->handler(4096)->fetch($server->url('/start'), $this->profile(), FetchMethod::CurlImpersonate);

            self::assertTrue($result->ok);
            self::assertSame($server->url('/final'), $result->finalUrl);
            self::assertSame(['/start', '/final'], $this->requestLog($requestLog));
            self::assertStringContainsString('alice', $result->body);
        } finally {
            $server->stop();
            $this->removeLog($requestLog);
        }
    }

    private function handler(int $maxResponseBytes): CurlImpersonateFetchHandler
    {
        return new CurlImpersonateFetchHandler(
            new FetchRuntimeConfig(
                curlImpersonateBinary: '/usr/bin/curl',
                respectRobots: false,
                maxResponseBytes: $maxResponseBytes,
            ),
            new ProcOpenProcessRunner($maxResponseBytes),
        );
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'curl-redirect-regression-test',
            displayName: 'Curl redirect regression test',
            baseUrl: 'http://127.0.0.1',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::CurlImpersonate],
            http: new HttpProfileDto(timeout: 10),
            respectRobots: false,
        );
    }

    private function sameOriginRedirectRouter(string $requestLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            file_put_contents(%s, $path . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($path === '/start') {
                header('Location: /done', true, 302);
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body>usable same origin response</body></html>';
            PHP,
            var_export($requestLog, true),
        );
    }

    private function crossOriginRedirectRouter(string $requestLog, string $destination): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            file_put_contents(%s, parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) . PHP_EOL, FILE_APPEND | LOCK_EX);
            header('Location: ' . %s, true, 302);
            exit;
            PHP,
            var_export($requestLog, true),
            var_export($destination, true),
        );
    }

    private function redirectCookieRouter(string $requestLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $requestLog = %s;
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            file_put_contents($requestLog, $path . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($path === '/start') {
                setcookie('user', 'alice');
                header('Location: /final', true, 302);
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body>user=' . htmlspecialchars((string) ($_COOKIE['user'] ?? 'anonymous')) . '</body></html>';
            PHP,
            var_export($requestLog, true),
        );
    }

    private function destinationRouter(string $requestLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            file_put_contents(%s, ($_SERVER['REQUEST_URI'] ?? '/') . PHP_EOL, FILE_APPEND | LOCK_EX);
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body>private destination</body></html>';
            PHP,
            var_export($requestLog, true),
        );
    }

    /** @return list<string> */
    private function requestLog(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        return $lines;
    }

    private function removeLog(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function requireCurlBinary(): void
    {
        if (! is_executable('/usr/bin/curl')) {
            self::markTestSkipped('The system curl binary is not installed.');
        }
    }
}
