<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Tools\TestRunner\ChangedTestMapper;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/tools/TestRunner/ChangedTestPlan.php';
require_once dirname(__DIR__, 3) . '/tools/TestRunner/ChangedTestMapper.php';

final class ChangedTestRunnerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/crawlerx-changed-tests-' . bin2hex(random_bytes(5));
        mkdir($this->root, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function test_adapter_changes_select_unit_feature_and_fixture_tests(): void
    {
        $this->addAdapter('ExampleSite', 'example-site');
        $this->addFile('tests/Unit/Adapters/ExampleSite/ExampleSiteTest.php');
        $this->addFile('tests/Feature/Adapters/ExampleSiteFieldsTest.php');
        $this->addFile('tests/Feature/AllAdaptersCrawlTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Adapters/ExampleSite/ExampleCrawler.php']);

        self::assertFalse($plan->fullSuite);
        self::assertContains('tests/Unit/Adapters/ExampleSite', $plan->testPaths);
        self::assertContains('tests/Feature/Adapters/ExampleSiteFieldsTest.php', $plan->testPaths);
        self::assertSame(['example-site'], $plan->fixtureSlugs);
        self::assertSame('~(?:example\\-site)~', $plan->fixtureFilter());
    }

    public function test_fetch_changes_select_the_fetch_unit_suite(): void
    {
        $this->addFile('tests/Unit/Fetch/FetchTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Fetch/FetchStrategy.php']);

        self::assertFalse($plan->fullSuite);
        self::assertSame(['tests/Unit/Fetch'], $plan->testPaths);
    }

    public function test_shared_concerns_select_the_full_adapter_unit_suite(): void
    {
        $this->addFile('tests/Unit/Adapters/ExampleSite/ExampleSiteTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Adapters/Concerns/ParsesHtml.php']);

        self::assertFalse($plan->fullSuite);
        self::assertSame(['tests/Unit/Adapters'], $plan->testPaths);
    }

    public function test_root_level_adapter_classes_select_the_full_adapter_unit_suite(): void
    {
        $this->addFile('tests/Unit/Adapters/ExampleSite/ExampleSiteTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Adapters/AbstractHtmlType.php']);

        self::assertFalse($plan->fullSuite);
        self::assertSame(['tests/Unit/Adapters'], $plan->testPaths);
    }

    public function test_shared_support_changes_select_adapter_and_support_unit_tests(): void
    {
        $this->addFile('tests/Unit/Adapters/ExampleSite/ExampleSiteTest.php');
        $this->addFile('tests/Unit/Support/ImageUrlTest.php');
        $this->addFile('tests/Unit/SupportNormalizerTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Support/ImageUrl.php']);

        self::assertFalse($plan->fullSuite);
        self::assertSame([
            'tests/Unit/Adapters',
            'tests/Unit/Support',
            'tests/Unit/SupportNormalizerTest.php',
        ], $plan->testPaths);
    }

    public function test_fixture_changes_select_the_matching_adapter_fixture_tests(): void
    {
        $this->addAdapter('ExampleSite', 'example-site', ['detail.html']);
        $this->addFile('tests/Feature/AllAdaptersCrawlTest.php');
        $this->addFile('tests/Feature/Snapshots/SnapshotParseTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['tests/Fixtures/example-site/detail.html']);

        self::assertFalse($plan->fullSuite);
        self::assertContains('tests/Feature/Snapshots/SnapshotParseTest.php', $plan->testPaths);
        self::assertSame(['example-site'], $plan->fixtureSlugs);
    }

    public function test_adapter_changes_select_the_shared_snapshot_feature_test(): void
    {
        $this->addAdapter('ExampleSite', 'example-site');
        $this->addFile('tests/Unit/Adapters/ExampleSite/ExampleSiteTest.php');
        $this->addFile('tests/Feature/Snapshots/SnapshotParseTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Adapters/ExampleSite/Types/Detail.php']);

        self::assertFalse($plan->fullSuite);
        self::assertContains('tests/Feature/Snapshots/SnapshotParseTest.php', $plan->testPaths);
    }

    public function test_runner_changes_select_unit_and_integration_tests(): void
    {
        $this->addFile('tests/Unit/Tools/ChangedTestRunnerTest.php');
        $this->addFile('tests/Unit/Tools/ChangedTestRunnerIntegrationTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['tools/test-changed.php']);

        self::assertFalse($plan->fullSuite);
        self::assertSame([
            'tests/Unit/Tools/ChangedTestRunnerIntegrationTest.php',
            'tests/Unit/Tools/ChangedTestRunnerTest.php',
        ], $plan->testPaths);
    }

    public function test_unmapped_files_fall_back_to_the_full_suite(): void
    {
        $plan = (new ChangedTestMapper($this->root))->map(['src/Unknown/Service.php']);

        self::assertTrue($plan->fullSuite);
        self::assertSame('No targeted mapping for src/Unknown/Service.php.', $plan->reason);
    }

    public function test_unknown_adapter_falls_back_even_when_snapshot_test_exists(): void
    {
        $this->addFile('tests/Feature/Snapshots/SnapshotParseTest.php');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Adapters/UnknownSite/Detail.php']);

        self::assertTrue($plan->fullSuite);
        self::assertSame('No targeted mapping for src/Adapters/UnknownSite/Detail.php.', $plan->reason);
    }

    public function test_removed_adapter_falls_back_even_when_snapshot_test_exists(): void
    {
        $this->addAdapter('RemovedSite', 'removed-site', ['detail.html']);
        $this->addFile('tests/Feature/Snapshots/SnapshotParseTest.php');
        $this->removeDirectory($this->root . '/src/Adapters/RemovedSite');

        $plan = (new ChangedTestMapper($this->root))->map(['src/Adapters/RemovedSite/Types/Detail.php']);

        self::assertTrue($plan->fullSuite);
        self::assertSame('No targeted mapping for src/Adapters/RemovedSite/Types/Detail.php.', $plan->reason);
    }

    public function test_an_empty_change_set_falls_back_to_the_full_suite(): void
    {
        $plan = (new ChangedTestMapper($this->root))->map([]);

        self::assertTrue($plan->fullSuite);
    }

    /**
     * @param list<string> $samples
     */
    private function addAdapter(string $site, string $slug, array $samples = []): void
    {
        $fixtureSamples = array_map(static fn(string $name): array => ['name' => $name], $samples);
        $manifest = [
            'slug' => $slug,
            'runtime' => ['fixtureSamples' => $fixtureSamples],
        ];

        $this->addFile('src/Adapters/' . $site . '/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    private function addFile(string $relativePath, string $contents = ''): void
    {
        $path = $this->root . '/' . $relativePath;
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($path, $contents);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir()) {
                $this->removeDirectory($entry->getPathname());
                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
