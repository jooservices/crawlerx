<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Cache\ResponseCache;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\Support\ExpiringArrayCache;
use JOOservices\CrawlerX\Tests\Support\LocalHttpServer;
use PHPUnit\Framework\TestCase;

final class HttpEnvironmentProxySecurityTest extends TestCase
{
    public function test_libcurl_environment_proxies_do_not_change_direct_fetch_or_cache_policy(): void
    {
        $targetLog = sys_get_temp_dir() . '/crawlerx-direct-proxy-target-' . bin2hex(random_bytes(8));
        $proxyLog = sys_get_temp_dir() . '/crawlerx-direct-proxy-' . bin2hex(random_bytes(8));
        $target = LocalHttpServer::start($this->router($targetLog, 'direct response body'));
        $proxy = LocalHttpServer::start($this->router($proxyLog, 'proxy response body'));
        $variables = [
            'CRAWLERX_HTTP_PROXY',
            'http_proxy',
            'https_proxy',
            'all_proxy',
            'HTTP_PROXY',
            'HTTPS_PROXY',
            'ALL_PROXY',
            'no_proxy',
            'NO_PROXY',
        ];
        $original = [];
        foreach ($variables as $name) {
            $original[$name] = getenv($name);
        }

        try {
            putenv('CRAWLERX_HTTP_PROXY');
            foreach (['http_proxy', 'https_proxy', 'all_proxy', 'HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY'] as $name) {
                putenv($name . '=' . $proxy->url('/'));
            }
            putenv('no_proxy=');
            putenv('NO_PROXY=');

            $cache = new ExpiringArrayCache(static fn(): float => 1.0);
            $handler = new ResponseCache(
                new HttpFetchHandler(new ClientFactory(), new CookieHandoffStore(), maxResponseBytes: 4096),
                $cache,
                60,
            );
            $profile = new SiteProfileDto(
                slug: 'environment-proxy-test',
                displayName: 'Environment proxy test',
                baseUrl: $target->url('/'),
                fetchProfile: FetchProfile::HttpOnly,
                fetchChain: [FetchMethod::Http],
                http: new HttpProfileDto(headers: ['Accept' => 'text/html']),
            );

            $redirect = $handler->fetch($target->url('/redirect'), $profile, FetchMethod::Http);
            $first = $handler->fetch($profile->baseUrl, $profile, FetchMethod::Http);
            $second = $handler->fetch($profile->baseUrl, $profile, FetchMethod::Http);

            self::assertTrue($redirect->ok);
            self::assertSame($target->url('/final'), $redirect->finalUrl);
            self::assertSame('<html><body>direct response body</body></html>', $first->body);
            self::assertSame($first->body, $second->body);
            self::assertSame(['/redirect', '/final', '/'], $this->requestLog($targetLog));
            self::assertSame([], is_file($proxyLog) ? $this->requestLog($proxyLog) : []);
        } finally {
            foreach ($original as $name => $value) {
                if (is_string($value)) {
                    putenv($name . '=' . $value);
                } else {
                    putenv($name);
                }
            }
            $target->stop();
            $proxy->stop();
            foreach ([$targetLog, $proxyLog] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function router(string $requestLog, string $body): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $requestLog = %s;
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            file_put_contents($requestLog, $path . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($path === '/redirect') {
                header('Location: /final', true, 302);
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo %s;
            PHP,
            var_export($requestLog, true),
            var_export('<html><body>' . $body . '</body></html>', true),
        );
    }

    /** @return list<string> */
    private function requestLog(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        return $lines;
    }
}
