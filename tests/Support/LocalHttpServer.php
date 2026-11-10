<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Support;

use RuntimeException;

final class LocalHttpServer
{
    /**
     * @param array<int, resource> $pipes
     * @param resource $process
     */
    private function __construct(
        private readonly string $directory,
        private readonly string $router,
        private readonly array $pipes,
        private mixed $process,
        private readonly int $port,
    ) {
    }

    public static function start(string $routerContents): self
    {
        $directory = sys_get_temp_dir() . '/crawlerx-http-test-' . bin2hex(random_bytes(8));
        if (! mkdir($directory) || file_put_contents($directory . '/router.php', $routerContents) === false) {
            throw new RuntimeException('Unable to create the local HTTP test router.');
        }

        $router = $directory . '/router.php';
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $port = self::freePort();
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $directory,
            );
            if (! is_resource($process)) {
                continue;
            }

            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            if (self::waitForPort($port, $process)) {
                return new self($directory, $router, $pipes, $process, $port);
            }

            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        unlink($router);
        rmdir($directory);

        throw new RuntimeException('The local HTTP test server did not start.');
    }

    public function url(string $path = '/'): string
    {
        return 'http://127.0.0.1:' . $this->port . $path;
    }

    public function stop(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        proc_terminate($this->process);
        foreach ([$this->pipes[1], $this->pipes[2]] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($this->process);
        $this->process = null;

        if (is_file($this->router)) {
            unlink($this->router);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function __destruct()
    {
        $this->stop();
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (! is_resource($socket)) {
            throw new RuntimeException($errorMessage, $errorCode);
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (! is_string($address)) {
            throw new RuntimeException('Unable to determine the local HTTP test port.');
        }

        return (int) substr($address, (int) strrpos($address, ':') + 1);
    }

    /** @param resource $process */
    private static function waitForPort(int $port, mixed $process): bool
    {
        $deadline = microtime(true) + 3.0;
        do {
            if (! proc_get_status($process)['running']) {
                return false;
            }

            $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return true;
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        return false;
    }
}
