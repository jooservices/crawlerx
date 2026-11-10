<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\ProcessResultDto;
use RuntimeException;

final class ProcOpenProcessRunner implements ProcessRunner
{
    public function __construct(private readonly int $maxOutputBytes = 64 * 1024 * 1024)
    {
    }

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
            $stdoutChunk = (string) stream_get_contents($pipes[1]);
            $stderrChunk = (string) stream_get_contents($pipes[2]);
            if (
                strlen($stdout) + strlen($stdoutChunk) > $this->maxOutputBytes
                || strlen($stderr) + strlen($stderrChunk) > min($this->maxOutputBytes, 1_048_576)
            ) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return new ProcessResultDto(1, '', 'process output exceeded the configured size limit.');
            }
            $stdout .= $stdoutChunk;
            $stderr .= $stderrChunk;

            if (! $status['running']) {
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($process);
                $stdoutChunk = (string) stream_get_contents($pipes[1]);
                $stderrChunk = (string) stream_get_contents($pipes[2]);
                if (
                    strlen($stdout) + strlen($stdoutChunk) > $this->maxOutputBytes
                    || strlen($stderr) + strlen($stderrChunk) > min($this->maxOutputBytes, 1_048_576)
                ) {
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);

                    return new ProcessResultDto(1, '', 'process output exceeded the configured size limit.');
                }
                $stdout .= $stdoutChunk;
                $stderr .= $stderrChunk;
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return new ProcessResultDto(exitCode: 124, stdout: $stdout, stderr: $stderr . "\nprocess timed out");
            }

            usleep(50_000);
        }

        $stdoutChunk = (string) stream_get_contents($pipes[1]);
        $stderrChunk = (string) stream_get_contents($pipes[2]);
        if (
            strlen($stdout) + strlen($stdoutChunk) > $this->maxOutputBytes
            || strlen($stderr) + strlen($stderrChunk) > min($this->maxOutputBytes, 1_048_576)
        ) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return new ProcessResultDto(1, '', 'process output exceeded the configured size limit.');
        }
        $stdout .= $stdoutChunk;
        $stderr .= $stderrChunk;
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
