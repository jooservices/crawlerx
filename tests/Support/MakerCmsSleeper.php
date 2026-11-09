<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types;

/**
 * Fixture-backed crawls do not need the production inter-page delay.
 *
 * The unqualified usleep() call in Listing resolves to this test-only function.
 */
function usleep(int $microseconds): void
{
}
