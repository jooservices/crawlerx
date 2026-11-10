<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Tools\ManifestConsistency\ManifestChecker;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/tools/ManifestChecker.php';

final class CheckManifestsTest extends TestCase
{
    private string $adaptersDirectory;

    protected function setUp(): void
    {
        $this->adaptersDirectory = sys_get_temp_dir() . '/crawlerx-manifests-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->adaptersDirectory));
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->adaptersDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->adaptersDirectory);
    }

    public function test_allows_a_valid_manifest(): void
    {
        $this->writeManifest('valid', $this->validManifest());

        self::assertSame([], $this->checker()->check());
    }

    public function test_allows_a_locale_path_in_the_base_url(): void
    {
        $this->writeManifest('missav', $this->validManifest(['base_url' => 'https://missav.ws/en']));

        self::assertSame([], $this->checker()->check());
    }

    public function test_allows_the_existing_underscore_slug(): void
    {
        $this->writeManifest('avfan-profiles', $this->validManifest(['slug' => 'avfan_profiles']));

        self::assertSame([], $this->checker()->check());
    }

    public function test_reports_a_missing_top_level_key(): void
    {
        $manifest = $this->validManifest();
        unset($manifest['description']);
        $this->writeManifest('missing-description', $manifest);

        self::assertSame(
            ['MISSING_KEY test-site description'],
            $this->checker()->check(),
        );
    }

    public function test_reports_an_invalid_slug(): void
    {
        $this->writeManifest('bad-slug', $this->validManifest(['slug' => 'Bad_Slug']));

        self::assertSame(
            ['BAD_SLUG bad-slug/manifest.json slug must match ^[a-z0-9][a-z0-9_-]*$'],
            $this->checker()->check(),
        );
    }

    public function test_reports_duplicate_slugs(): void
    {
        $manifest = $this->validManifest(['slug' => 'shared-site']);
        $this->writeManifest('first', $manifest);
        $this->writeManifest('second', $manifest);

        self::assertSame(
            ['DUPLICATE_SLUG shared-site second/manifest.json duplicates first/manifest.json'],
            $this->checker()->check(),
        );
    }

    public function test_reports_a_non_http_base_url(): void
    {
        $this->writeManifest('bad-url', $this->validManifest(['base_url' => 'ftp://example.test']));

        self::assertSame(
            ['BAD_BASE_URL test-site base_url must be an absolute HTTP(S) URL without credentials, query, or fragment'],
            $this->checker()->check(),
        );
    }

    public function test_reports_a_missing_adapter_class(): void
    {
        $adapter = [
            'class' => 'JOOservices\\CrawlerX\\Tests\\Unit\\Tools\\MissingManifestAdapter',
            'capabilities' => ['detail'],
        ];
        $this->writeManifest('missing-class', $this->validManifest(['adapter' => $adapter]));

        self::assertSame(
            ['MISSING_CLASS test-site adapter.class does not name an existing class'],
            $this->checker()->check(),
        );
    }

    public function test_reports_missing_capabilities(): void
    {
        $adapter = ['class' => \stdClass::class, 'capabilities' => []];
        $this->writeManifest('no-capabilities', $this->validManifest(['adapter' => $adapter]));

        self::assertSame(
            ['NO_CAPABILITIES test-site adapter.capabilities must be a non-empty list of strings'],
            $this->checker()->check(),
        );
    }

    public function test_sorts_problems(): void
    {
        $this->writeManifest('bad', ['slug' => 'Bad_Slug', 'base_url' => 'ftp://example.test']);

        $problems = $this->checker()->check();
        $sortedProblems = $problems;
        sort($sortedProblems, SORT_STRING);

        self::assertNotEmpty($problems);
        self::assertSame($sortedProblems, $problems);
    }

    public function test_invalid_json_throws_a_runtime_exception(): void
    {
        $directory = $this->adaptersDirectory . '/invalid-json';
        self::assertTrue(mkdir($directory));
        self::assertNotFalse(file_put_contents($directory . '/manifest.json', '{'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Manifest contains invalid JSON: invalid-json/manifest.json');

        $this->checker()->check();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validManifest(array $overrides = []): array
    {
        return array_replace([
            'slug' => 'test-site',
            'name' => 'Test Site',
            'version' => '1.0.0',
            'description' => 'Test adapter manifest',
            'author' => 'CrawlerX',
            'requires' => [],
            'adapter' => ['class' => \stdClass::class, 'capabilities' => ['detail']],
            'base_url' => 'https://example.test',
            'pagination' => [],
            'runtime' => [],
            'type' => 'html',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(string $directory, array $manifest): void
    {
        $path = $this->adaptersDirectory . '/' . $directory;
        self::assertTrue(mkdir($path, recursive: true));
        self::assertNotFalse(file_put_contents(
            $path . '/manifest.json',
            json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        ));
    }

    private function checker(): ManifestChecker
    {
        return new ManifestChecker($this->adaptersDirectory);
    }
}
