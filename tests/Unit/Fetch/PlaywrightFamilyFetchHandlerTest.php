<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
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
            public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
            {
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
}
