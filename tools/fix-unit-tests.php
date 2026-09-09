#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests/Unit'));

foreach ($files as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $code = file_get_contents($path);
    if ($code === false || ! str_contains($code, 'Mockery')) {
        continue;
    }

    $code = preg_replace('/\nuse GuzzleHttp\\\\Psr7\\\\Response;.*?\n/s', "\n", $code) ?? $code;
    $code = preg_replace('/\nuse JOOservices\\\\CrawlerX\\\\Contracts\\\\CrawlHttpClient;.*?\n/s', "\n", $code) ?? $code;
    $code = preg_replace('/\nuse JOOservices\\\\CrawlerX\\\\Contracts\\\\CrawlHttpResponse;.*?\n/s', "\n", $code) ?? $code;
    $code = preg_replace('/\nuse Mockery\\\\MockInterface;.*?\n/s', "\n", $code) ?? $code;
    $code = preg_replace('/\nuse Mockery;.*?\n/s', "\n", $code) ?? $code;
    $code = preg_replace('/\n    private CrawlHttpClient&MockInterface \$client;.*?\n/s', "\n", $code) ?? $code;
    $code = preg_replace('/\n    protected function setUp\(\): void\s*\{[^}]+\}\s*/s', "\n", $code) ?? $code;
    $code = preg_replace('/\n    protected function tearDown\(\): void\s*\{[^}]+\}\s*/s', "\n", $code) ?? $code;
    $code = preg_replace('/\n    private function makeResponse\(string \$html\):[^\}]+\}\s*/s', "\n", $code) ?? $code;
    $code = preg_replace('/\n    \/\*\*[^*]*\*\/\n    private function clientWithFixture\([^\}]+\}\s*/s', "\n", $code) ?? $code;
    $code = preg_replace('/\n    private function clientWithHtml\([^\}]+\}\s*/s', "\n", $code) ?? $code;
    $code = preg_replace('/\n    private function makeListing\(\): Listing\s*\{\s*return new Listing\(\$this->client[^}]+\}\s*/s', "\n", $code) ?? $code;

    $code = str_replace('$this->client', '$this->clientWithFixture(\'PLACEHOLDER\')', $code);
    $code = str_replace('new Listing($this->clientWithFixture(\'PLACEHOLDER\'), new UrlNormalizer)', 'new Listing($this->clientWithFixture(\'onejav/listing-page-1.html\'))', $code);
    $code = str_replace('$this->clientWithFixture(\'PLACEHOLDER\')', '$client', $code);

    // Replace mock response setup blocks
    $code = preg_replace(
        '/\$response = Mockery::mock\([^;]+;\s*\$response->shouldReceive\([^;]+;\s*\$this->client->shouldReceive\([^;]+;/s',
        '$client = $this->clientWithFixture(\'onejav/listing-page-1.html\');',
        $code,
    ) ?? $code;

    $code = preg_replace(
        '/\$this->client->shouldReceive\(\'get\'\)[^;]+;/',
        '',
        $code,
    ) ?? $code;

    $code = preg_replace(
        '/\$response = Mockery::mock\([^;]+;\s*\$response->shouldReceive\([^;]+;/s',
        '',
        $code,
    ) ?? $code;

    if (str_contains($code, 'Mockery') || str_contains($code, 'ResponseWrapperInterface')) {
        echo "MANUAL: {$path}\n";
        continue;
    }

    file_put_contents($path, $code);
    echo "Fixed: {$path}\n";
}
