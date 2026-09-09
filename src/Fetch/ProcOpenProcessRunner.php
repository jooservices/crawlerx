<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\ProcessResultDto;
use RuntimeException;

final class ProcOpenProcessRunner implements ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
    {
        if ($command === []) {
            throw new RuntimeException('Process command must not be empty.');
        }

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes, $cwd);
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start process: ' . implode(' ', $command));
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (! $status['running']) {
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($process);
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return new ProcessResultDto(exitCode: 124, stdout: $stdout, stderr: $stderr . "\nprocess timed out");
            }

            usleep(50_000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return new ProcessResultDto(
            exitCode: $status['exitcode'] >= 0 ? $status['exitcode'] : $exitCode,
            stdout: $stdout,
            stderr: $stderr,
        );
    }
}
