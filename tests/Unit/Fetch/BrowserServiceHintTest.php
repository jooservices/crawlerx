<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\BrowserServiceHint;
use PHPUnit\Framework\TestCase;

final class BrowserServiceHintTest extends TestCase
{
    public function test_a_missing_playwright_package_becomes_a_configuration_message(): void
    {
        $stderr = "node:internal/modules/package_json_reader:331\nError [ERR_MODULE_NOT_FOUND]: Cannot find package 'playwright'";

        $message = BrowserServiceHint::describe($stderr, 'fallback');

        self::assertStringContainsString('PLAYWRIGHT_URL', $message);
        self::assertStringNotContainsString('package_json_reader', $message);
    }

    public function test_a_missing_node_binary_becomes_a_configuration_message(): void
    {
        self::assertStringContainsString('PLAYWRIGHT_URL', BrowserServiceHint::describe('sh: 1: node: not found', 'fallback'));
    }

    public function test_other_failures_keep_their_text(): void
    {
        self::assertSame('net::ERR_CONNECTION_RESET', BrowserServiceHint::describe('net::ERR_CONNECTION_RESET', 'fallback'));
    }

    public function test_empty_stderr_uses_the_fallback(): void
    {
        self::assertSame('fallback', BrowserServiceHint::describe('', 'fallback'));
    }
}
