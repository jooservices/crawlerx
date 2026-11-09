<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools;

use DateTimeImmutable;
use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FixturesMetaChecker
{
    /** @var list<string> */
    private const REQUIRED_KEYS = [
        'source_url',
        'final_url',
        'captured_at',
        'http_status',
        'content_hash',
        'target_type',
        'fetch_method',
        'sanitized',
    ];

    /**
     * @return array{
     *     exit_code: int,
     *     issues: list<array{code: string, path: string, detail: string}>,
     *     baseline_file_count: int,
     *     baseline_counts: array<string, int>,
     *     missing_directory: bool
     * }
     */
    public function check(string $fixturesDirectory, string $baselineFile): array
    {
        $root = realpath($fixturesDirectory);
        if ($root === false || !is_dir($root)) {
            return [
                'exit_code' => 2,
                'issues' => [],
                'baseline_file_count' => 0,
                'baseline_counts' => [],
                'missing_directory' => true,
            ];
        }

        $fixtureFiles = [];
        $metadataFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root) + 1));

            if (str_ends_with($relativePath, '.meta.json')) {
                $metadataFiles[$relativePath] = $absolutePath;
                continue;
            }

            $fixtureFiles[$relativePath] = $absolutePath;
        }

        ksort($fixtureFiles, SORT_STRING);
        ksort($metadataFiles, SORT_STRING);

        $baselinePaths = $this->readBaseline($baselineFile);
        $issues = [];
        $baselinedPaths = [];
        $baselineCounts = [];

        foreach ($fixtureFiles as $relativePath => $absolutePath) {
            $metadataRelativePath = $relativePath . '.meta.json';
            $problems = isset($metadataFiles[$metadataRelativePath])
                ? $this->validateMetadata($absolutePath, $metadataFiles[$metadataRelativePath], $relativePath)
                : [[
                    'code' => 'MISSING_META',
                    'path' => $relativePath,
                    'detail' => 'missing sibling metadata file',
                ]];

            $this->recordProblems($problems, $relativePath, $baselinePaths, $issues, $baselinedPaths, $baselineCounts);
        }

        foreach ($metadataFiles as $metadataRelativePath => $metadataAbsolutePath) {
            $relativePath = substr($metadataRelativePath, 0, -strlen('.meta.json'));
            if (isset($fixtureFiles[$relativePath])) {
                continue;
            }

            $problems = $this->validateMetadata(null, $metadataAbsolutePath, $relativePath);
            $problems[] = [
                'code' => 'BAD_VALUE',
                'path' => $relativePath,
                'detail' => 'metadata has no matching fixture file',
            ];
            $this->recordProblems($problems, $relativePath, $baselinePaths, $issues, $baselinedPaths, $baselineCounts);
        }

        usort(
            $issues,
            static fn(array $left, array $right): int
            => [$left['code'], $left['path'], $left['detail']] <=> [$right['code'], $right['path'], $right['detail']],
        );
        ksort($baselineCounts, SORT_STRING);

        return [
            'exit_code' => $issues === [] ? 0 : 1,
            'issues' => $issues,
            'baseline_file_count' => count($baselinedPaths),
            'baseline_counts' => $baselineCounts,
            'missing_directory' => false,
        ];
    }

    /**
     * @return list<array{code: string, path: string, detail: string}>
     */
    private function validateMetadata(?string $fixturePath, string $metadataPath, string $relativePath): array
    {
        $contents = file_get_contents($metadataPath);
        if ($contents === false) {
            return [[
                'code' => 'BAD_JSON',
                'path' => $relativePath,
                'detail' => 'metadata file could not be read',
            ]];
        }

        try {
            $metadata = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [[
                'code' => 'BAD_JSON',
                'path' => $relativePath,
                'detail' => 'invalid JSON: ' . $exception->getMessage(),
            ]];
        }

        if (!$metadata instanceof \stdClass) {
            return [[
                'code' => 'BAD_VALUE',
                'path' => $relativePath,
                'detail' => 'metadata must be a JSON object',
            ]];
        }

        $values = get_object_vars($metadata);
        $issues = [];
        $missingKeys = array_values(array_filter(
            self::REQUIRED_KEYS,
            static fn(string $key): bool => !array_key_exists($key, $values),
        ));

        if ($missingKeys !== []) {
            $issues[] = [
                'code' => 'MISSING_KEY',
                'path' => $relativePath,
                'detail' => 'missing keys: ' . implode(', ', $missingKeys),
            ];
        }

        $badValues = [];
        foreach (['source_url', 'final_url'] as $key) {
            if (array_key_exists($key, $values) && !$this->isHttpUrl($values[$key])) {
                $badValues[] = $key;
            }
        }

        if (array_key_exists('captured_at', $values) && !$this->isIso8601DateTime($values['captured_at'])) {
            $badValues[] = 'captured_at';
        }

        if (array_key_exists('http_status', $values)
            && (!is_int($values['http_status']) || $values['http_status'] < 100 || $values['http_status'] > 599)) {
            $badValues[] = 'http_status';
        }

        $hasValidHash = isset($values['content_hash'])
            && is_string($values['content_hash'])
            && preg_match('/\A[a-f0-9]{64}\z/', $values['content_hash']) === 1;
        if (array_key_exists('content_hash', $values) && !$hasValidHash) {
            $badValues[] = 'content_hash';
        }

        foreach (['target_type', 'fetch_method'] as $key) {
            if (array_key_exists($key, $values) && (!is_string($values[$key]) || trim($values[$key]) === '')) {
                $badValues[] = $key;
            }
        }

        if (array_key_exists('sanitized', $values) && !is_bool($values['sanitized'])) {
            $badValues[] = 'sanitized';
        }

        if ($badValues !== []) {
            $issues[] = [
                'code' => 'BAD_VALUE',
                'path' => $relativePath,
                'detail' => 'invalid values: ' . implode(', ', $badValues),
            ];
        }

        if ($hasValidHash) {
            if ($fixturePath === null) {
                $issues[] = [
                    'code' => 'BAD_VALUE',
                    'path' => $relativePath,
                    'detail' => 'content hash cannot be checked without a fixture file',
                ];
            } elseif (!is_readable($fixturePath)) {
                $issues[] = [
                    'code' => 'BAD_VALUE',
                    'path' => $relativePath,
                    'detail' => 'fixture file could not be read for hashing',
                ];
            } else {
                $actualHash = hash_file('sha256', $fixturePath);
                if ($actualHash === false) {
                    $issues[] = [
                        'code' => 'BAD_VALUE',
                        'path' => $relativePath,
                        'detail' => 'fixture file could not be hashed',
                    ];
                } elseif (!hash_equals($values['content_hash'], $actualHash)) {
                    $issues[] = [
                        'code' => 'HASH_MISMATCH',
                        'path' => $relativePath,
                        'detail' => 'content_hash does not match fixture bytes',
                    ];
                }
            }
        }

        return $issues;
    }

    private function isHttpUrl(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        $parts = parse_url($value);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && $parts['host'] !== '';
    }

    private function isIso8601DateTime(mixed $value): bool
    {
        if (!is_string($value)
            || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})\z/', $value) !== 1) {
            return false;
        }

        try {
            new DateTimeImmutable($value);
        } catch (\Exception) {
            return false;
        }

        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }

    /** @return array<string, true> */
    private function readBaseline(string $baselineFile): array
    {
        if (!is_file($baselineFile) || !is_readable($baselineFile)) {
            return [];
        }

        $contents = file($baselineFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($contents === false) {
            return [];
        }

        $paths = [];
        foreach ($contents as $line) {
            $path = trim($line);
            if ($path === '' || str_starts_with($path, '#') || str_starts_with($path, '/')
                || in_array('..', explode('/', str_replace('\\', '/', $path)), true)) {
                continue;
            }

            $paths[$path] = true;
        }

        return $paths;
    }

    /**
     * @param list<array{code: string, path: string, detail: string}> $problems
     * @param array<string, true> $baselinePaths
     * @param list<array{code: string, path: string, detail: string}> $issues
     * @param array<string, true> $baselinedPaths
     * @param array<string, int> $baselineCounts
     */
    private function recordProblems(
        array $problems,
        string $relativePath,
        array $baselinePaths,
        array &$issues,
        array &$baselinedPaths,
        array &$baselineCounts,
    ): void {
        if (isset($baselinePaths[$relativePath])) {
            if ($problems !== []) {
                $baselinedPaths[$relativePath] = true;
                foreach ($problems as $problem) {
                    $baselineCounts[$problem['code']] = ($baselineCounts[$problem['code']] ?? 0) + 1;
                }
            }

            return;
        }

        array_push($issues, ...$problems);
    }
}
