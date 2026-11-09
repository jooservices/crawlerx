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

final class CurlImpersonateSecurityTest extends TestCase
{
    public function test_curl_does_not_follow_redirects_to_an_unguarded_destination(): void
    {
        $this->requireCurlBinary();
        $requestLog = sys_get_temp_dir() . '/crawlerx-curl-redirect-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->redirectRouter($requestLog));

        try {
            $result = $this->handler(4096)->fetch($server->url('/start'), $this->profile(), FetchMethod::CurlImpersonate);

            self::assertFalse($result->ok);
            self::assertSame(['/start'], $this->requestLog($requestLog));
        } finally {
            $server->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    public function test_curl_output_is_stopped_at_the_configured_body_limit(): void
    {
        $this->requireCurlBinary();
        $sentBytesLog = sys_get_temp_dir() . '/crawlerx-curl-body-limit-' . bin2hex(random_bytes(8));
        $maxResponseBytes = 8192;
        $server = LocalHttpServer::start($this->chunkedResponseRouter($sentBytesLog));

        try {
            $result = $this->handler($maxResponseBytes)->fetch($server->url('/large'), $this->profile(), FetchMethod::CurlImpersonate);

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

    private function handler(int $maxResponseBytes): CurlImpersonateFetchHandler
    {
        return new CurlImpersonateFetchHandler(
            new FetchRuntimeConfig(curlImpersonateBinary: '/usr/bin/curl', maxResponseBytes: $maxResponseBytes),
            new ProcOpenProcessRunner($maxResponseBytes),
        );
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'curl-security-test',
            displayName: 'Curl security test',
            baseUrl: 'http://127.0.0.1',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::CurlImpersonate],
            http: new HttpProfileDto(timeout: 10),
        );
    }

    private function redirectRouter(string $requestLog): string
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
            echo '<html><body>private redirect response body</body></html>';
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

        self::fail('The curl response server did not record its sent byte count.');
    }

    private function requireCurlBinary(): void
    {
        if (! is_executable('/usr/bin/curl')) {
            self::markTestSkipped('The system curl binary is not installed.');
        }
    }
}
