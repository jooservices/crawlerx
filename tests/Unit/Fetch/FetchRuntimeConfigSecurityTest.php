<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use PHPUnit\Framework\TestCase;

final class FetchRuntimeConfigSecurityTest extends TestCase
{
    private ?string $originalMaxResponseBytes = null;

    protected function setUp(): void
    {
        $value = getenv('CRAWLERX_MAX_RESPONSE_BYTES');
        $this->originalMaxResponseBytes = is_string($value) ? $value : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalMaxResponseBytes === null) {
            putenv('CRAWLERX_MAX_RESPONSE_BYTES');
        } else {
            putenv('CRAWLERX_MAX_RESPONSE_BYTES=' . $this->originalMaxResponseBytes);
        }

        parent::tearDown();
    }

    public function test_response_body_limit_defaults_to_ten_megabytes_and_accepts_a_bounded_override(): void
    {
        putenv('CRAWLERX_MAX_RESPONSE_BYTES');
        self::assertSame(10_485_760, FetchRuntimeConfig::fromEnvironment()->maxResponseBytes);

        putenv('CRAWLERX_MAX_RESPONSE_BYTES=2048');
        self::assertSame(2048, FetchRuntimeConfig::fromEnvironment()->maxResponseBytes);
    }

    public function test_invalid_or_excessive_response_body_limit_uses_the_safe_default(): void
    {
        putenv('CRAWLERX_MAX_RESPONSE_BYTES=0');
        self::assertSame(10_485_760, FetchRuntimeConfig::fromEnvironment()->maxResponseBytes);

        putenv('CRAWLERX_MAX_RESPONSE_BYTES=104857601');
        self::assertSame(10_485_760, FetchRuntimeConfig::fromEnvironment()->maxResponseBytes);
    }
}
