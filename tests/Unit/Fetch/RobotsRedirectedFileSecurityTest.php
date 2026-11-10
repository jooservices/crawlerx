<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Tests\Support\LocalHttpServer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RobotsRedirectedFileSecurityTest extends TestCase
{
    private string|false $originalRespectRobots = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalRespectRobots = getenv('CRAWLERX_RESPECT_ROBOTS');
        putenv('CRAWLERX_RESPECT_ROBOTS=true');
        ClientBuilder::clearFake();
        CrawlerXFactory::reset();
    }

    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        CrawlerXFactory::reset();
        if (is_string($this->originalRespectRobots)) {
            putenv('CRAWLERX_RESPECT_ROBOTS=' . $this->originalRespectRobots);
        } else {
            putenv('CRAWLERX_RESPECT_ROBOTS');
        }
        parent::tearDown();
    }

    public function test_redirected_robots_txt_rules_block_a_page_before_it_is_requested(): void
    {
        $requestLog = sys_get_temp_dir() . '/crawlerx-robots-redirect-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->router($requestLog));
        $defaultChain = new ReflectionMethod(CrawlerXFactory::class, 'defaultFetchChain');
        $chain = $defaultChain->invoke(null);

        self::assertInstanceOf(FetchFallbackChain::class, $chain);

        try {
            try {
                $chain->fetch($server->url('/blocked'), $this->profile($server->url('/')), [FetchMethod::Http]);
                self::fail('A rule from the redirected robots file must block its disallowed page.');
            } catch (RobotsDisallowedException) {
                self::assertSame(['/robots.txt', '/robots-next'], $this->requestLog($requestLog));
            }
        } finally {
            $server->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    public function test_each_robots_redirect_passes_through_the_outbound_guard(): void
    {
        $requestLog = sys_get_temp_dir() . '/crawlerx-robots-guard-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->router($requestLog));
        $checkedUrls = [];
        CrawlerXFactory::configure(beforeRobotsRequest: static function (string $url) use (&$checkedUrls): bool {
            $checkedUrls[] = $url;

            return ! str_ends_with($url, '/robots-next');
        });
        $defaultChain = new ReflectionMethod(CrawlerXFactory::class, 'defaultFetchChain');
        $chain = $defaultChain->invoke(null);

        self::assertInstanceOf(FetchFallbackChain::class, $chain);

        try {
            $result = $chain->fetch($server->url('/page'), $this->profile($server->url('/')), [FetchMethod::Http]);

            self::assertTrue($result->ok);
            self::assertSame(['/robots.txt', '/page'], $this->requestLog($requestLog));
            self::assertSame([
                $server->url('/robots.txt'),
                $server->url('/robots-next'),
            ], $checkedUrls);
        } finally {
            $server->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    public function test_robots_redirects_stop_after_five_hops_and_an_unavailable_policy_fails_open(): void
    {
        $requestLog = sys_get_temp_dir() . '/crawlerx-robots-hop-limit-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->redirectLoopRouter($requestLog));
        $robotsRequestCount = 0;
        CrawlerXFactory::configure(beforeRobotsRequest: static function (string $url) use (&$robotsRequestCount): bool {
            if (str_ends_with($url, '/robots.txt')) {
                ++$robotsRequestCount;

                return $robotsRequestCount === 1;
            }

            return true;
        });
        $defaultChain = new ReflectionMethod(CrawlerXFactory::class, 'defaultFetchChain');
        $chain = $defaultChain->invoke(null);

        self::assertInstanceOf(FetchFallbackChain::class, $chain);

        try {
            $result = $chain->fetch($server->url('/page'), $this->profile($server->url('/')), [FetchMethod::Http]);

            self::assertTrue($result->ok);
            self::assertSame([
                '/robots.txt',
                '/robots-1',
                '/robots-2',
                '/robots-3',
                '/robots-4',
                '/robots-5',
                '/page',
            ], $this->requestLog($requestLog));
            self::assertSame(1, $robotsRequestCount);
        } finally {
            $server->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    private function router(string $requestLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $requestLog = %s;
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            file_put_contents($requestLog, $path . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($path === '/robots.txt') {
                header('Location: /robots-next', true, 302);
                exit;
            }
            if ($path === '/robots-next') {
                header('Content-Type: text/plain; charset=utf-8');
                echo "User-agent: *\nDisallow: /blocked\n";
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body>disallowed page</body></html>';
            PHP,
            var_export($requestLog, true),
        );
    }

    private function redirectLoopRouter(string $requestLog): string
    {
        return sprintf(
            <<<'PHP'
            <?php
            $requestLog = %s;
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            file_put_contents($requestLog, $path . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($path === '/robots.txt') {
                header('Location: /robots-1', true, 302);
                exit;
            }
            if (preg_match('/^\/robots-(\d+)$/', $path, $matches) === 1) {
                header('Location: /robots-' . ((int) $matches[1] + 1), true, 302);
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><body>page after redirect limit</body></html>';
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

    private function profile(string $baseUrl): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'robots-redirect-test',
            displayName: 'Robots redirect test',
            baseUrl: $baseUrl,
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: ['User-Agent' => 'CrawlerX robots redirect test']),
        );
    }
}
