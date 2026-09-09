<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

use JOOservices\CrawlerX\Dto\ProcessResultDto;

interface ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto;
}
