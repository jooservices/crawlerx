<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Support;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Services\ClientFactory;

final class FixtureResponder
{
    public static function enableFake(): void
    {
        ClientBuilder::fake();
    }

    public static function disableFake(): void
    {
        ClientBuilder::clearFake();
    }

    public static function for(string $method, string $url): self
    {
        return new self($method, $url);
    }

    public function __construct(
        private readonly string $method,
        private readonly string $url,
    ) {
    }

    public function file(string $fixturePath, int $status = 200, array $headers = []): void
    {
        $fullPath = __DIR__ . '/../Fixtures/' . ltrim($fixturePath, '/');
        $html = file_get_contents($fullPath);
        if ($html === false) {
            throw new \RuntimeException("Fixture not found: {$fullPath}");
        }

        $this->body($html, $status, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function body(string $html, int $status = 200, array $headers = []): void
    {
        ClientBuilder::respond(
            $this->method,
            $this->url,
            (new TestResponseSequence())->push(TestResponse::make($status, $headers, $html)),
        );
    }

    public static function crawlHttpClient(): CrawlHttpClient
    {
        return (new ClientFactory())->factory([]);
    }
}
