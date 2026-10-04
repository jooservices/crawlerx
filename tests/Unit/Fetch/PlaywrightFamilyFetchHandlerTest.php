<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\ProcessResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use PHPUnit\Framework\TestCase;

final class PlaywrightFamilyFetchHandlerTest extends TestCase
{
    public function test_parses_sidecar_json_and_cookies(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'pw-script-');
        self::assertNotFalse($script);
        file_put_contents($script, '// stub');

        $runner = new class implements ProcessRunner {
            public int $timeout = 0;

            public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
            {
                $this->timeout = $timeoutSeconds;

                return new ProcessResultDto(
                    exitCode: 0,
                    stdout: json_encode([
                        'status' => 200,
                        'finalUrl' => 'https://en.jable.tv/videos/abc/',
                        'html' => '<html><body><h1>Real</h1></body></html>',
                        'elapsedMs' => 12,
                        'challenge' => false,
                        'cookies' => [['name' => 'cf_clearance', 'value' => 'tok']],
                    ], JSON_THROW_ON_ERROR),
                );
            }
        };

        $handler = new PlaywrightFamilyFetchHandler(
            new FetchRuntimeConfig(playwrightScript: $script),
            $runner,
        );

        $result = $handler->fetch(
            'https://en.jable.tv/videos/abc/',
            new SiteProfileDto(
                slug: 'jable',
                displayName: 'Jable',
                baseUrl: 'https://en.jable.tv',
                fetchProfile: FetchProfile::BrowserLikely,
                fetchChain: FetchMethod::browserChain(),
                http: new HttpProfileDto(),
            ),
            FetchMethod::PlaywrightStealth,
        );

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::PlaywrightStealth, $result->methodUsed);
        self::assertSame('tok', $result->cookies['cf_clearance']);
        self::assertSame(90, $runner->timeout);
        unlink($script);
    }

    public function test_fails_when_script_missing(): void
    {
        $handler = new PlaywrightFamilyFetchHandler(
            new FetchRuntimeConfig(playwrightScript: '/tmp/missing-playwright-fetch.mjs'),
            new class implements ProcessRunner {
                public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
                {
                    throw new \RuntimeException('runner should not be called');
                }
            },
        );

        $result = $handler->fetch(
            'https://example.test',
            new SiteProfileDto(
                slug: 'demo',
                displayName: 'Demo',
                baseUrl: 'https://example.test',
                fetchProfile: FetchProfile::BrowserLikely,
                fetchChain: FetchMethod::browserChain(),
                http: new HttpProfileDto(),
            ),
            FetchMethod::Playwright,
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('not found', (string) $result->error);
    }

    public function test_chrome_stealth_respects_the_site_headless_profile(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'pw-script-');
        self::assertNotFalse($script);
        file_put_contents($script, '// stub');

        $runner = new class implements ProcessRunner {
            /** @var array<string, mixed> */
            public array $config = [];

            public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
            {
                $configPath = substr($command[2], strlen('--config='));
                $this->config = json_decode((string) file_get_contents($configPath), true, flags: JSON_THROW_ON_ERROR);

                return new ProcessResultDto(
                    exitCode: 0,
                    stdout: json_encode([
                        'status' => 200,
                        'finalUrl' => 'https://example.test',
                        'html' => '<html><body>usable</body></html>',
                    ], JSON_THROW_ON_ERROR),
                );
            }
        };
        $handler = new PlaywrightFamilyFetchHandler(new FetchRuntimeConfig(playwrightScript: $script), $runner);
        $profile = new SiteProfileDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::BrowserLikely,
            fetchChain: FetchMethod::browserChain(),
            http: new HttpProfileDto(),
            playwright: new PlaywrightProfileDto(headless: true),
        );

        $result = $handler->fetch('https://example.test', $profile, FetchMethod::ChromeStealth);

        self::assertTrue($result->ok);
        self::assertTrue($runner->config['headless']);
        unlink($script);
    }

    public function test_passes_ready_contract_and_returns_storage_state(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'pw-script-');
        self::assertNotFalse($script);
        file_put_contents($script, '// stub');

        $runner = new class implements ProcessRunner {
            /** @var array<string, mixed> */
            public array $config = [];

            public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
            {
                $configPath = substr($command[2], strlen('--config='));
                $this->config = json_decode((string) file_get_contents($configPath), true, flags: JSON_THROW_ON_ERROR);

                return new ProcessResultDto(
                    exitCode: 0,
                    stdout: json_encode([
                        'status' => 200,
                        'finalUrl' => 'https://example.test',
                        'html' => '<html><body><p id="movie">usable</p></body></html>',
                        'storageState' => ['cookies' => [['name' => 'fixture_session', 'value' => 'fixture-value']]],
                        'userAgent' => 'Fixture-Agent/1.0',
                    ], JSON_THROW_ON_ERROR),
                );
            }
        };

        $handler = new PlaywrightFamilyFetchHandler(new FetchRuntimeConfig(playwrightScript: $script), $runner);
        $result = $handler->fetch(
            'https://example.test',
            new SiteProfileDto(
                slug: 'demo',
                displayName: 'Demo',
                baseUrl: 'https://example.test',
                fetchProfile: FetchProfile::BrowserLikely,
                fetchChain: FetchMethod::browserChain(),
                http: new HttpProfileDto(),
                playwright: new PlaywrightProfileDto(readyTimeoutMs: 3210, blockResources: false),
                readyMarkers: ['detail' => ['#movie']],
            ),
            FetchMethod::Playwright,
            new \JOOservices\CrawlerX\Dto\CrawlOptionsDto(
                storageState: ['cookies' => [['name' => 'fixture_session', 'value' => 'fixture-value']]],
                readyMarkers: ['#movie'],
            ),
        );

        self::assertTrue($result->ok);
        self::assertSame('fixture_session', $result->storageState['cookies'][0]['name']);
        self::assertSame('Fixture-Agent/1.0', $result->userAgent);
        self::assertSame(3210, $runner->config['readyTimeoutMs']);
        self::assertSame(['#movie'], $runner->config['readyMarkers']);
        self::assertFalse($runner->config['blockResources']);
        self::assertSame('fixture_session', $runner->config['storageState']['cookies'][0]['name']);
        unlink($script);
    }
}
