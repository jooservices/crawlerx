<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Tests\Support\ManifestFixtureCatalog;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FixtureProvenanceTest extends TestCase
{
    public function test_manifest_fixtures_are_live_captures_with_verified_content(): void
    {
        $registry = new FileAdapterManifestRegistry();

        foreach ($registry->slugs() as $slug) {
            $manifest = $registry->get($slug);
            self::assertNotNull($manifest);

            foreach ($manifest->fixtureSamples as $sample) {
                $path = ManifestFixtureCatalog::fixturePath($slug, $sample->name);
                self::assertFileExists($path, "Missing fixture for {$slug}: {$sample->name}");
                self::assertFileExists($path . '.meta.json', "Missing provenance for {$slug}: {$sample->name}");

                /** @var array<string, mixed> $metadata */
                $metadata = json_decode((string) file_get_contents($path . '.meta.json'), true, flags: JSON_THROW_ON_ERROR);

                self::assertSame($sample->url, $metadata['source_url'] ?? null);
                self::assertSame($sample->type, $metadata['target_type'] ?? null);
                self::assertFalse($metadata['sanitized'] ?? true);
                self::assertContains($metadata['fetch_method'] ?? null, ['http', 'playwright', 'puppeteer', 'flaresolverr']);
                self::assertSame(hash_file('sha256', $path), $metadata['content_hash'] ?? null);
                self::assertIsString($metadata['final_url'] ?? null);
                self::assertNotSame('', trim($metadata['final_url']));
                self::assertIsInt($metadata['http_status'] ?? null);
                self::assertGreaterThanOrEqual(200, $metadata['http_status']);
                self::assertLessThan(300, $metadata['http_status']);
            }
        }
    }

    public function test_fixture_directory_contains_only_registered_live_captures(): void
    {
        $registry = new FileAdapterManifestRegistry();
        $registered = [];
        $fixturesRoot = dirname(__DIR__) . '/Fixtures/';

        foreach ($registry->slugs() as $slug) {
            $manifest = $registry->get($slug);
            self::assertNotNull($manifest);

            foreach ($manifest->fixtureSamples as $sample) {
                $registered[ManifestFixtureCatalog::fixtureRelativePath($slug, $sample->name)] = true;
            }
        }

        $fixtures = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            rtrim($fixturesRoot, '/'),
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        /** @var SplFileInfo $fixture */
        foreach ($fixtures as $fixture) {
            $path = $fixture->getPathname();
            if (str_ends_with($path, '.meta.json') || (! str_ends_with($path, '.html') && ! str_ends_with($path, '.json'))) {
                continue;
            }

            $relativePath = substr($path, strlen($fixturesRoot));
            self::assertArrayHasKey($relativePath, $registered, "Unregistered fixture: {$relativePath}");
        }
    }
}
