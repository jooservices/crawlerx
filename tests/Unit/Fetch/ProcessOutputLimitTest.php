<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use PHPUnit\Framework\TestCase;

final class ProcessOutputLimitTest extends TestCase
{
    public function test_process_output_is_stopped_at_its_configured_limit(): void
    {
        $result = (new ProcOpenProcessRunner(128))->run([
            PHP_BINARY,
            '-r',
            'echo str_repeat("x", 1024);',
        ]);

        self::assertSame(1, $result->exitCode);
        self::assertSame('', $result->stdout);
        self::assertStringContainsString('size limit', $result->stderr);
    }
}
