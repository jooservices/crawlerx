<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\TestRunner;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class ChangedTestMapper
{
    public function __construct(private string $repositoryRoot)
    {
    }

    /**
     * @param list<string> $changedFiles
     */
    public function map(array $changedFiles): ChangedTestPlan
    {
        if ($changedFiles === []) {
            return new ChangedTestPlan(true, [], [], 'No changed files were detected.');
        }

        $testPaths = [];
        $fixtureSlugs = [];

        foreach ($changedFiles as $changedFile) {
            $path = $this->normalizePath($changedFile);

            if ($this->isSharedAdapterCode($path)) {
                if (! $this->addExistingPath('tests/Unit/Adapters', $testPaths)) {
                    return $this->unmapped($path);
                }

                if (str_starts_with($path, 'src/Support/')) {
                    foreach ([
                        'tests/Unit/Support',
                        'tests/Unit/SupportNormalizerTest.php',
                        'tests/Unit/QueryPageUrlTest.php',
                    ] as $supportTestPath) {
                        $this->addExistingPath($supportTestPath, $testPaths);
                    }
                }

                continue;
            }

            if (preg_match('#^src/Fetch(?:/|$)#', $path) === 1) {
                if (! $this->addExistingPath('tests/Unit/Fetch', $testPaths)) {
                    return $this->unmapped($path);
                }

                continue;
            }

            if (preg_match('#^src/Adapters/([^/]+)(?:/|$)#', $path, $matches) === 1) {
                if (! $this->mapAdapter($matches[1], $testPaths, $fixtureSlugs)) {
                    return $this->unmapped($path);
                }

                continue;
            }

            if (preg_match('#^tests/Fixtures/([^/]+)/([^/]+)$#', $path, $matches) === 1) {
                $site = $this->fixtureAdapter($matches[1], $matches[2]);
                if ($site === null || ! $this->mapAdapter($site, $testPaths, $fixtureSlugs)) {
                    return $this->unmapped($path);
                }

                continue;
            }

            if (preg_match('#^tests/Unit/Adapters/Concerns(?:/|$)#', $path) === 1) {
                if (! $this->addExistingPath('tests/Unit/Adapters', $testPaths)) {
                    return $this->unmapped($path);
                }

                continue;
            }

            if (preg_match('#^tests/(?:Unit|Feature)/#', $path) === 1) {
                if (! $this->addTestPath($path, $testPaths)) {
                    return $this->unmapped($path);
                }

                continue;
            }

            if ($path === 'tools/test-changed.php' || str_starts_with($path, 'tools/TestRunner/')) {
                if (! $this->addExistingPath('tests/Unit/Tools/ChangedTestRunnerTest.php', $testPaths)
                    || ! $this->addExistingPath('tests/Unit/Tools/ChangedTestRunnerIntegrationTest.php', $testPaths)
                ) {
                    return $this->unmapped($path);
                }

                continue;
            }

            return $this->unmapped($path);
        }

        $testPaths = array_values(array_unique($testPaths));
        $fixtureSlugs = array_values(array_unique($fixtureSlugs));

        if ($testPaths === [] && $fixtureSlugs === []) {
            return new ChangedTestPlan(true, [], [], 'No matching test files were found.');
        }

        sort($testPaths);
        sort($fixtureSlugs);

        return new ChangedTestPlan(false, $testPaths, $fixtureSlugs);
    }

    /**
     * @param list<string> $testPaths
     * @param list<string> $fixtureSlugs
     */
    private function mapAdapter(string $site, array &$testPaths, array &$fixtureSlugs): bool
    {
        $slug = $this->adapterSlug($site);
        if (! is_dir($this->repositoryRoot . '/src/Adapters/' . $site) || $slug === null) {
            return false;
        }

        $unitPath = 'tests/Unit/Adapters/' . $site;
        $mapped = $this->addExistingPath($unitPath, $testPaths);

        foreach ($this->featureTests() as $featureTest) {
            if ($this->featureTestMatchesAdapter($featureTest, $site, $slug)) {
                $testPaths[] = $featureTest;
                $mapped = true;
            }
        }

        if ($slug !== null && $this->hasFixtureTests()) {
            $fixtureSlugs[] = $slug;
            $mapped = $this->hasFixtureSamples($site) || $mapped;
        }

        if ($mapped) {
            $this->addExistingPath('tests/Feature/Snapshots/SnapshotParseTest.php', $testPaths);
        }

        return $mapped;
    }

    private function isSharedAdapterCode(string $path): bool
    {
        return preg_match('#^src/Adapters/[^/]+\.php$#', $path) === 1
            || preg_match('#^src/Adapters/Concerns(?:/|$)#', $path) === 1
            || preg_match('#^src/Adapters/Shared(?:/|$)#', $path) === 1
            || preg_match('#^src/Support(?:/|$)#', $path) === 1;
    }

    private function hasFixtureSamples(string $site): bool
    {
        $manifest = $this->readManifest('src/Adapters/' . $site . '/manifest.json');
        $samples = $manifest['runtime']['fixtureSamples'] ?? null;

        return is_array($samples) && $samples !== [];
    }

    private function adapterSlug(string $site): ?string
    {
        $manifest = $this->readManifest('src/Adapters/' . $site . '/manifest.json');
        $slug = $manifest['slug'] ?? null;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    private function fixtureAdapter(string $folder, string $fileName): ?string
    {
        if (str_ends_with($fileName, '.meta.json')) {
            $fileName = substr($fileName, 0, -strlen('.meta.json'));
        }

        $adapters = $this->repositoryRoot . '/src/Adapters';
        if (! is_dir($adapters)) {
            return null;
        }

        foreach (new FilesystemIterator($adapters, FilesystemIterator::SKIP_DOTS) as $adapterDirectory) {
            if (! $adapterDirectory->isDir()) {
                continue;
            }

            $site = $adapterDirectory->getFilename();
            $manifest = $this->readManifest('src/Adapters/' . $site . '/manifest.json');
            $slug = $manifest['slug'] ?? null;
            $samples = $manifest['runtime']['fixtureSamples'] ?? null;

            if (! is_string($slug) || ! is_array($samples) || $this->normalizeName($slug) !== $this->normalizeName($folder)) {
                continue;
            }

            foreach ($samples as $sample) {
                if (! is_array($sample)) {
                    continue;
                }

                if (($sample['name'] ?? null) === $fileName) {
                    return $site;
                }

                foreach ($sample['followUps'] ?? [] as $followUp) {
                    if (is_array($followUp) && ($followUp['name'] ?? null) === $fileName) {
                        return $site;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function featureTests(): array
    {
        $directory = $this->repositoryRoot . '/tests/Feature';
        if (! is_dir($directory)) {
            return [];
        }

        $tests = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $tests[] = 'tests/Feature/' . substr($file->getPathname(), strlen($directory) + 1);
        }

        sort($tests);

        return $tests;
    }

    private function featureTestMatchesAdapter(string $featureTest, string $site, ?string $slug): bool
    {
        $name = $this->normalizeName(pathinfo($featureTest, PATHINFO_FILENAME));
        if (str_starts_with($name, $this->normalizeName($site))
            || ($slug !== null && str_starts_with($name, $this->normalizeName($slug)))
        ) {
            return true;
        }

        $contents = file_get_contents($this->repositoryRoot . '/' . $featureTest);
        if (! is_string($contents)) {
            return false;
        }

        return $this->containsIdentifier($contents, $site)
            || ($slug !== null && $this->containsIdentifier($contents, $slug));
    }

    private function containsIdentifier(string $contents, string $identifier): bool
    {
        return preg_match('/(?<![a-z0-9])' . preg_quote($identifier, '/') . '(?![a-z0-9])/i', $contents) === 1;
    }

    /**
     * @param list<string> $destination
     */
    private function addExistingPath(string $path, array &$destination): bool
    {
        if (! $this->pathExists($path)) {
            return false;
        }

        $destination[] = $path;

        return true;
    }

    /**
     * @param list<string> $destination
     */
    private function addTestPath(string $path, array &$destination): bool
    {
        if ($this->pathExists($path)) {
            $destination[] = $path;

            return true;
        }

        $parent = dirname($path);
        if ($this->pathExists($parent)) {
            $destination[] = $parent;

            return true;
        }

        return false;
    }

    private function pathExists(string $relativePath): bool
    {
        return file_exists($this->repositoryRoot . '/' . $relativePath);
    }

    private function hasFixtureTests(): bool
    {
        return $this->pathExists('tests/Unit/ManifestFixtureExecutorTest.php')
            || $this->pathExists('tests/Feature/AllAdaptersCrawlTest.php');
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $relativePath): array
    {
        $path = $this->repositoryRoot . '/' . $relativePath;
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return [];
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($manifest) ? $manifest : [];
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, './') ? substr($normalized, 2) : ltrim($normalized, '/');
    }

    private function normalizeName(string $name): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $name));
    }

    private function unmapped(string $path): ChangedTestPlan
    {
        return new ChangedTestPlan(true, [], [], "No targeted mapping for {$path}.");
    }
}
