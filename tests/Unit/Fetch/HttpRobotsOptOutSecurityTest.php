<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\Support\LocalHttpServer;
use PHPUnit\Framework\TestCase;

final class HttpRobotsOptOutSecurityTest extends TestCase
{
    public function test_site_profile_can_opt_out_of_robots_checks_for_redirect_hops(): void
    {
        $this->assertRedirectAllowedWithRobotsDisabled(globalRespectRobots: true, siteRespectRobots: false);
    }

    public function test_global_setting_can_opt_out_of_robots_checks_for_redirect_hops(): void
    {
        $this->assertRedirectAllowedWithRobotsDisabled(globalRespectRobots: false, siteRespectRobots: null);
    }

    private function assertRedirectAllowedWithRobotsDisabled(bool $globalRespectRobots, ?bool $siteRespectRobots): void
    {
        $requestLog = sys_get_temp_dir() . '/crawlerx-robots-opt-out-' . bin2hex(random_bytes(8));
        $server = LocalHttpServer::start($this->redirectRouter($requestLog));
        $robots = new RobotsGuard(static fn(string $url): ?string => "User-agent: *\nDisallow: /private\n");
        $runtime = new FetchRuntimeConfig(respectRobots: $globalRespectRobots);
        $handler = new HttpFetchHandler(
            new ClientFactory(),
            maxResponseBytes: 4096,
            robotsGuard: $robots,
            defaultRespectRobots: $globalRespectRobots,
        );
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $handler],
            runtime: $runtime,
            robotsGuard: $robots,
            maxTransientRetries: 0,
        );

        try {
            $result = $chain->fetch(
                $server->url('/start'),
                $this->profile($siteRespectRobots),
                [FetchMethod::Http],
            );

            self::assertTrue($result->ok);
            self::assertSame($server->url('/private'), $result->finalUrl);
            self::assertSame(['/start', '/private'], $this->requestLog($requestLog));
        } finally {
            $server->stop();
            if (is_file($requestLog)) {
                unlink($requestLog);
            }
        }
    }

    private function profile(?bool $respectRobots): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'robots-opt-out-test',
            displayName: 'Robots opt-out test',
            baseUrl: 'http://127.0.0.1',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(headers: ['User-Agent' => 'CrawlerX robots opt-out test']),
            respectRobots: $respectRobots,
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

    /** @return list<string> */
    private function requestLog(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        return $lines;
    }
}
