<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;

final class ChangedTestRunnerIntegrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/crawlerx-changed-runner-' . bin2hex(random_bytes(5));
        mkdir($this->root, recursive: true);
        $this->createRepository();
        $this->commitBaseline();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function test_fixture_change_runs_adapter_tests_and_manifest_fixture_tests(): void
    {
        $this->addFile('tests/Fixtures/mgstage/detail.html', '<html>changed</html>');

        [$exitCode, $output, $error] = $this->runTestRunner();

        self::assertSame(0, $exitCode, $error);
        self::assertStringContainsString('tests/Unit/Adapters/Mgstage', $output);
        self::assertStringContainsString('ManifestFixtureExecutorTest.php', $output);
        self::assertStringContainsString('AllAdaptersCrawlTest.php', $output);
    }

    public function test_adapter_change_runs_shared_feature_tests_that_reference_its_slug(): void
    {
        $this->addFile('src/Adapters/Xcity/XcityAdapter.php', '<?php // changed');

        [$exitCode, $output, $error] = $this->runTestRunner();

        self::assertSame(0, $exitCode, $error);
        self::assertStringContainsString('tests/Unit/Adapters/Xcity', $output);
        self::assertStringContainsString('tests/Feature/CapabilityCrawlTest.php', $output);
    }

    public function test_cross_adapter_fixture_rename_runs_tests_for_both_adapters(): void
    {
        mkdir($this->root . '/tests/Fixtures/xcity', recursive: true);
        rename(
            $this->root . '/tests/Fixtures/mgstage/detail.html',
            $this->root . '/tests/Fixtures/xcity/detail.html',
        );

        [$exitCode, $output, $error] = $this->runTestRunner();

        self::assertSame(0, $exitCode, $error);
        self::assertStringContainsString('tests/Unit/Adapters/Mgstage', $output);
        self::assertStringContainsString('tests/Unit/Adapters/Xcity', $output);
    }

    private function createRepository(): void
    {
        $sourceRoot = dirname(__DIR__, 3);
        foreach ([
            'tools/test-changed.php',
            'tools/TestRunner/ChangedTestMapper.php',
            'tools/TestRunner/ChangedTestPlan.php',
        ] as $runnerFile) {
            $contents = file_get_contents($sourceRoot . '/' . $runnerFile);
            self::assertIsString($contents);
            $this->addFile($runnerFile, $contents);
        }

        $this->addFile(
            'vendor/bin/phpunit',
            "<?php\nfwrite(STDOUT, json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR) . PHP_EOL);\n",
        );
        $this->addAdapter('Mgstage', 'mgstage', 'detail.html');
        $this->addAdapter('Xcity', 'xcity', 'detail.html');

        foreach ([
            'tests/Unit/Adapters/Mgstage/FieldsTest.php',
            'tests/Unit/Adapters/Mgstage/ImagesTest.php',
            'tests/Unit/Adapters/Xcity/FieldsTest.php',
            'tests/Feature/AllAdaptersCrawlTest.php',
            'tests/Feature/CapabilityCrawlTest.php',
            'tests/Unit/ManifestFixtureExecutorTest.php',
        ] as $testFile) {
            $contents = $testFile === 'tests/Feature/CapabilityCrawlTest.php' ? "<?php\n// xcity capability data set\n" : "<?php\n// test stub\n";
            $this->addFile($testFile, $contents);
        }

        $this->addFile('tests/Fixtures/mgstage/detail.html', '<html>original</html>');
    }

    private function addAdapter(string $site, string $slug, string $fixtureName): void
    {
        $manifest = [
            'slug' => $slug,
            'runtime' => [
                'fixtureSamples' => [
                    ['name' => $fixtureName],
                ],
            ],
        ];

        $this->addFile('src/Adapters/' . $site . '/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    private function addFile(string $relativePath, string $contents): void
    {
        $path = $this->root . '/' . $relativePath;
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($path, $contents);
    }

    private function commitBaseline(): void
    {
        foreach ([
            ['git', 'init', '--quiet'],
            ['git', 'config', 'user.name', 'Changed Test Runner Test'],
            ['git', 'config', 'user.email', 'changed-test-runner@example.test'],
            ['git', 'add', '-A'],
            ['git', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'baseline'],
        ] as $command) {
            [$exitCode, , $error] = $this->execute($command);
            self::assertSame(0, $exitCode, $error);
        }
    }

    /**
     * @return array{int, string, string}
     */
    private function runTestRunner(): array
    {
        return $this->execute([PHP_BINARY, $this->root . '/tools/test-changed.php', '--base=HEAD']);
    }

    /**
     * @param list<string> $command
     * @return array{int, string, string}
     */
    private function execute(array $command): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        self::assertIsResource($process);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            proc_close($process),
            is_string($output) ? $output : '',
            is_string($error) ? $error : '',
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir()) {
                $this->removeDirectory($entry->getPathname());
                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
