<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Tools\CheckChangelog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/CheckChangelog.php';

#[CoversClass(CheckChangelog::class)]
final class CheckChangelogTest extends TestCase
{
    public function test_accepts_valid_changelog_with_unreleased_and_descending_versions(): void
    {
        $changelog = <<<'CHANGELOG'
## [Unreleased]

### Added
- Add a new tool.

## [1.10.0] - 2026-11-10

### Fixed
- Fix the latest release.

## [1.2.0] - 2026-10-01

### Security
- Fix an older release.
CHANGELOG;

        self::assertSame([], CheckChangelog::validate($changelog));
    }

    public function test_reports_bad_version_heading_with_its_line(): void
    {
        $diagnostics = CheckChangelog::validate("## [1.0.0] - 2026-02-30\n\n### Added\n- Entry.\n");

        self::assertSame('BAD_VERSION_HEADING', $diagnostics[0]['code']);
        self::assertSame(1, $diagnostics[0]['line']);
    }

    public function test_reports_disallowed_section_heading(): void
    {
        $changelog = "## [Unreleased]\n\n### Documentation\n- Update the docs.\n";

        self::assertSame('BAD_SECTION', CheckChangelog::validate($changelog)[0]['code']);
    }

    public function test_reports_empty_section(): void
    {
        $changelog = "## [Unreleased]\n\n### Added\n\n## [1.0.0] - 2026-01-01\n\n### Fixed\n- Entry.\n";

        $diagnostics = CheckChangelog::validate($changelog);

        self::assertSame('EMPTY_SECTION', $diagnostics[0]['code']);
        self::assertSame(3, $diagnostics[0]['line']);
    }

    public function test_reports_repeated_section_within_a_version(): void
    {
        $changelog = "## [Unreleased]\n\n### Added\n- First entry.\n\n### Added\n- Second entry.\n";

        $diagnostics = CheckChangelog::validate($changelog);

        self::assertSame('DUPLICATE_SECTION', $diagnostics[0]['code']);
        self::assertSame(6, $diagnostics[0]['line']);
    }

    public function test_reports_identical_bullet_lines_anywhere_in_the_file(): void
    {
        $changelog = "## [Unreleased]\n\n### Added\n- Same entry.\n\n## [1.0.0] - 2026-01-01\n\n### Fixed\n- Same entry.\n";

        $diagnostics = CheckChangelog::validate($changelog);

        self::assertSame('DUPLICATE_ENTRY', $diagnostics[0]['code']);
        self::assertSame(9, $diagnostics[0]['line']);
    }

    public function test_reports_versions_that_are_not_in_descending_order(): void
    {
        $changelog = "## [1.0.0] - 2026-01-01\n\n### Added\n- Older release.\n\n## [1.2.0] - 2026-02-01\n\n### Fixed\n- Newer release.\n";

        $diagnostics = CheckChangelog::validate($changelog);

        self::assertSame('BAD_ORDER', $diagnostics[0]['code']);
        self::assertSame(6, $diagnostics[0]['line']);
    }

    public function test_reports_unreleased_after_a_release_as_bad_order(): void
    {
        $changelog = "## [1.0.0] - 2026-01-01\n\n### Added\n- Release entry.\n\n## [Unreleased]\n\n### Changed\n- Unreleased entry.\n";

        $diagnostics = CheckChangelog::validate($changelog);

        self::assertSame('BAD_ORDER', $diagnostics[0]['code']);
        self::assertSame(6, $diagnostics[0]['line']);
    }

    public function test_sorts_diagnostics_by_line_number(): void
    {
        $changelog = "## [1.0.0] - 2026-01-01\n\n### Added\n- Older release.\n\n## [1.2.0] - 2026-02-01\n\n### Documentation\n- Newer release.\n";

        $diagnostics = CheckChangelog::validate($changelog);

        self::assertSame([6, 8], array_column($diagnostics, 'line'));
        self::assertSame(['BAD_ORDER', 'BAD_SECTION'], array_column($diagnostics, 'code'));
    }
}
