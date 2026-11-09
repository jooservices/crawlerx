<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Documentation;

use JOOservices\CrawlerX\Tests\TestCase;

final class ArchitectureDocumentationTest extends TestCase
{
    public function test_deptrac_documentation_matches_the_enforced_ci_workflow(): void
    {
        $workflow = $this->readProjectFile('.github/workflows/ci.yml');
        $readme = $this->readProjectFile('README.md');
        $architecture = $this->readProjectFile('docs/architecture.md');
        $decision = $this->readProjectFile('docs/decisions/005-deptrac-report-only.md');

        self::assertStringContainsString('composer lint:deptrac', $workflow);
        self::assertStringContainsString('needs: [test, security, architecture]', $workflow);
        self::assertStringContainsString('required', $readme);
        self::assertStringContainsString('`Coverage upload` check fails', $readme);
        self::assertStringContainsString('required', $architecture);
        self::assertStringContainsString('`Coverage upload` check fails', $architecture);
        self::assertStringContainsString('**Status:** Superseded', $decision);
        self::assertStringNotContainsString('report-only `Architecture (Deptrac)` job', $readme);
    }

    public function test_adapter_dependency_documentation_matches_the_current_layer_boundary(): void
    {
        $abstractType = $this->readProjectFile('src/Adapters/AbstractType.php');
        $architecture = $this->readProjectFile('docs/architecture.md');
        $decision = $this->readProjectFile('docs/decisions/005-deptrac-report-only.md');

        self::assertStringContainsString('Support\\ChallengeResponseDetector', $abstractType);
        self::assertStringContainsString('ChallengeResponseDetector', $architecture);
        self::assertStringNotContainsString('AbstractType` to `ChallengeDetector`', $architecture);
        self::assertStringNotContainsString('known Adapters-to-Fetch dependency', $decision);
    }

    public function test_lifecycle_diagram_shows_success_terminal_and_guard_exit_paths(): void
    {
        $diagram = $this->mermaidDiagram($this->readProjectFile('docs/architecture.md'));

        self::assertStringContainsString('loop each planned method while robots allow and no success or terminal outcome', $diagram);
        self::assertStringContainsString('alt guard rejects with synthetic 429', $diagram);
        self::assertStringContainsString('do not call Handler.fetch()', $diagram);
        self::assertStringContainsString('break success', $diagram);
        self::assertStringContainsString('break terminal outcome', $diagram);
        self::assertStringContainsString('eligible non-terminal failure', $diagram);
    }

    private function readProjectFile(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $relativePath);
        if (! is_string($contents)) {
            self::fail(sprintf('Unable to read project file: %s', $relativePath));
        }

        return $contents;
    }

    private function mermaidDiagram(string $markdown): string
    {
        $start = strpos($markdown, "```mermaid\n");
        self::assertNotFalse($start, 'Architecture guide must include its Mermaid lifecycle diagram.');
        $start += strlen("```mermaid\n");

        $end = strpos($markdown, "\n```", $start);
        self::assertNotFalse($end, 'Architecture guide must close its Mermaid lifecycle diagram.');

        return substr($markdown, $start, $end - $start);
    }
}
