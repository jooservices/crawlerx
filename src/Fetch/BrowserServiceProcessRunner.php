<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerX\Contracts\ProcessRunner;
use JOOservices\CrawlerX\Dto\ProcessResultDto;
use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use RuntimeException;
use Throwable;

final class BrowserServiceProcessRunner implements ProcessRunner
{
    public function __construct(
        private readonly string $endpoint,
        private readonly ?ClientInterface $client = null,
    ) {
    }

    public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
    {
        $configPath = $this->configPath($command);
        $config = file_get_contents($configPath);
        if ($config === false) {
            throw new RuntimeException('Unable to read browser sidecar config: ' . $configPath);
        }

        $script = isset($command[1]) && str_contains($command[1], 'puppeteer') ? 'puppeteer' : 'playwright';
        $payload = json_encode([
            'script' => $script,
            'config' => json_decode($config, true, flags: JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);

        try {
            $client = $this->client ?? ClientBuilder::create()->withTimeout($timeoutSeconds)->build();
            $response = $client->sendRequest(new Request('POST', rtrim($this->endpoint, '/') . '/fetch', [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], $payload));
            $raw = (string) $response->getBody();
        } catch (Throwable $exception) {
            return new ProcessResultDto(1, '', 'Browser service request failed: ' . $exception->getMessage());
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return new ProcessResultDto(1, '', 'Browser service returned invalid JSON');
        }

        return new ProcessResultDto(
            exitCode: is_numeric($decoded['exitCode'] ?? null) ? (int) $decoded['exitCode'] : 1,
            stdout: is_string($decoded['stdout'] ?? null) ? $decoded['stdout'] : '',
            stderr: is_string($decoded['stderr'] ?? null) ? $decoded['stderr'] : '',
        );
    }

    /** @param list<string> $command */
    private function configPath(array $command): string
    {
        foreach ($command as $argument) {
            if (str_starts_with($argument, '--config=')) {
                return substr($argument, strlen('--config='));
            }
        }

        throw new RuntimeException('Browser command is missing --config.');
    }
}
