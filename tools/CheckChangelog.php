<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools;

final class CheckChangelog
{
    /** @var list<string> */
    private const ALLOWED_SECTIONS = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security'];

    /**
     * @return list<array{code: string, line: int, detail: string}>
     */
    public static function validate(string $contents): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));

        $diagnostics = [];
        $versions = [];
        $sections = [];
        $headings = [];
        $sectionCounts = [];
        $bulletLines = [];
        $versionGroup = 0;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if (preg_match('/^ {0,3}##(?!#)(.*)$/D', $line, $matches) === 1) {
                $versionGroup++;
                $headings[$index] = true;
                $title = trim($matches[1]);

                if ($title === '[Unreleased]') {
                    $versions[] = ['kind' => 'unreleased', 'parts' => [], 'line' => $lineNumber];
                } elseif (preg_match('/^\[(\d+)\.(\d+)\.(\d+)\] - (\d{4})-(\d{2})-(\d{2})$/D', $title, $parts) === 1
                    && checkdate((int) $parts[5], (int) $parts[6], (int) $parts[4])) {
                    $versions[] = [
                        'kind' => 'release',
                        'parts' => [$parts[1], $parts[2], $parts[3]],
                        'line' => $lineNumber,
                    ];
                } else {
                    $diagnostics[] = [
                        'code' => 'BAD_VERSION_HEADING',
                        'line' => $lineNumber,
                        'detail' => "Expected '## [Unreleased]' or '## [x.y.z] - YYYY-MM-DD' with a valid date.",
                    ];
                }

                continue;
            }

            if (preg_match('/^ {0,3}###(?!#)(.*)$/D', $line, $matches) === 1) {
                $headings[$index] = true;
                $title = trim($matches[1]);
                $sections[] = ['line' => $lineNumber, 'index' => $index, 'title' => $title];

                if (!in_array($title, self::ALLOWED_SECTIONS, true)) {
                    $diagnostics[] = [
                        'code' => 'BAD_SECTION',
                        'line' => $lineNumber,
                        'detail' => "Unknown section heading '{$title}'.",
                    ];
                } else {
                    $key = $versionGroup . ':' . $title;
                    $sectionCounts[$key] = ($sectionCounts[$key] ?? 0) + 1;
                    if ($sectionCounts[$key] > 1) {
                        $diagnostics[] = [
                            'code' => 'DUPLICATE_SECTION',
                            'line' => $lineNumber,
                            'detail' => "Section '{$title}' repeats within this version.",
                        ];
                    }
                }

                continue;
            }

            if (preg_match('/^[\t ]*[-*+][\t ]+\S/', $line) === 1) {
                if (isset($bulletLines[$line])) {
                    $diagnostics[] = [
                        'code' => 'DUPLICATE_ENTRY',
                        'line' => $lineNumber,
                        'detail' => 'Duplicate bullet entry.',
                    ];
                } else {
                    $bulletLines[$line] = true;
                }
            }
        }

        self::validateVersionOrder($versions, $diagnostics);

        foreach ($sections as $section) {
            $hasContent = false;
            for ($index = $section['index'] + 1, $count = count($lines); $index < $count; $index++) {
                if (isset($headings[$index])) {
                    break;
                }

                if (trim($lines[$index]) !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if (!$hasContent) {
                $diagnostics[] = [
                    'code' => 'EMPTY_SECTION',
                    'line' => $section['line'],
                    'detail' => "Section '{$section['title']}' has no content.",
                ];
            }
        }

        usort(
            $diagnostics,
            static fn(array $left, array $right): int => $left['line'] <=> $right['line']
                ?: strcmp($left['code'], $right['code']),
        );

        return $diagnostics;
    }

    /**
     * @param list<array{kind: string, parts: list<string>, line: int}> $versions
     * @param list<array{code: string, line: int, detail: string}> $diagnostics
     */
    private static function validateVersionOrder(array $versions, array &$diagnostics): void
    {
        $unreleasedSeen = false;
        $previousRelease = null;

        foreach ($versions as $version) {
            if ($version['kind'] === 'unreleased') {
                if ($unreleasedSeen || $previousRelease !== null) {
                    $diagnostics[] = [
                        'code' => 'BAD_ORDER',
                        'line' => $version['line'],
                        'detail' => '[Unreleased] must appear once before release sections.',
                    ];
                }

                $unreleasedSeen = true;

                continue;
            }

            if ($previousRelease !== null && self::compareVersions($previousRelease, $version['parts']) <= 0) {
                $diagnostics[] = [
                    'code' => 'BAD_ORDER',
                    'line' => $version['line'],
                    'detail' => 'Version sections must be in descending order.',
                ];
            }

            $previousRelease = $version['parts'];
        }
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function compareVersions(array $left, array $right): int
    {
        foreach ($left as $index => $part) {
            $leftPart = ltrim($part, '0');
            $rightPart = ltrim($right[$index], '0');
            $leftPart = $leftPart === '' ? '0' : $leftPart;
            $rightPart = $rightPart === '' ? '0' : $rightPart;

            if (strlen($leftPart) !== strlen($rightPart)) {
                return strlen($leftPart) <=> strlen($rightPart);
            }

            $comparison = strcmp($leftPart, $rightPart);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }
}
