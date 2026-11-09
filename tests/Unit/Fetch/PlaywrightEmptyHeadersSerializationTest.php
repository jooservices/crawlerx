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

final class PlaywrightEmptyHeadersSerializationTest extends TestCase
{
    public function test_empty_headers_are_serialized_as_an_object(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'crawlerx-pw-empty-headers-');
        self::assertNotFalse($script);
        file_put_contents($script, '// test script');

        try {
            $runner = new class implements ProcessRunner {
                public mixed $extraHttpHeaders = null;

                public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
                {
                    $argument = $command[2] ?? '';
                    $configPath = str_starts_with($argument, '--config=') ? substr($argument, 9) : '';
                    $configJson = $configPath !== '' ? file_get_contents($configPath) : false;
                    $config = is_string($configJson) ? json_decode($configJson, flags: JSON_THROW_ON_ERROR) : null;
                    $this->extraHttpHeaders = $config?->extraHttpHeaders ?? null;

                    return new ProcessResultDto(
                        exitCode: 0,
                        stdout: json_encode([
                            'status' => 200,
                            'finalUrl' => 'https://example.test/page',
                            'html' => '<html><body>page is ready</body></html>',
                            'challenge' => false,
                        ], JSON_THROW_ON_ERROR),
                    );
                }
            };

            $handler = new PlaywrightFamilyFetchHandler(
                new FetchRuntimeConfig(playwrightScript: $script),
                $runner,
            );
            $profile = new SiteProfileDto(
                slug: 'example',
                displayName: 'Example',
                baseUrl: 'https://example.test',
                fetchProfile: FetchProfile::BrowserLikely,
                fetchChain: FetchMethod::browserChain(),
                http: new HttpProfileDto(),
            );

            $result = $handler->fetch('https://example.test/page', $profile, FetchMethod::Playwright);

            self::assertTrue($result->ok);
            self::assertInstanceOf(\stdClass::class, $runner->extraHttpHeaders);
            self::assertSame([], get_object_vars($runner->extraHttpHeaders));
        } finally {
            unlink($script);
        }
    }
}
