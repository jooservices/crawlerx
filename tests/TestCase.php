<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Http\MappedPrefetchedCrawlHttpClient;
use JOOservices\CrawlerX\Http\PrefetchedCrawlHttpClient;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function loadFixture(string $path): string
    {
        $fullPath = __DIR__ . '/Fixtures/' . ltrim($path, '/');
        self::assertFileExists($fullPath, "Fixture not found: {$fullPath}");

        $content = file_get_contents($fullPath);
        self::assertIsString($content);
        self::assertNotSame('', $content, "Fixture is empty: {$fullPath}");

        return $content;
    }

    protected function fixturePath(string $path): string
    {
        return __DIR__ . '/Fixtures/' . ltrim($path, '/');
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function clientWithFixture(string $fixture, int $status = 200, array $headers = []): CrawlHttpClient
    {
        return $this->clientWithHtml($this->loadFixture($fixture), $status, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function clientWithHtml(string $html, int $status = 200, array $headers = []): CrawlHttpClient
    {
        if ($headers === []) {
            $headers = ['Content-Type' => 'text/html; charset=utf-8'];
        }

        return new PrefetchedCrawlHttpClient($html, $status, null, $headers);
    }

    /**
     * @param  array<string, array{body: string, status?: int, headers?: array<string, string>}>  $responses
     */
    protected function clientWithMappedResponses(array $responses): CrawlHttpClient
    {
        return new MappedPrefetchedCrawlHttpClient($responses);
    }
}
