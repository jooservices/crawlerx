<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools;

use JsonException;
use RuntimeException;

/** Holds the consistency check's CLI result. */
final readonly class ReadmeSitesCheckResult
{
    /**
     * Creates a result with its CLI status and diagnostics.
     *
     * @param list<string> $messages
     */
    public function __construct(
        public int $exitCode,
        public array $messages,
    ) {
    }
}

/** Compares README supported-site names with adapter manifest identifiers. */
final class ReadmeSitesChecker
{
    /** Reads both inputs and returns sorted consistency errors. */
    public function check(string $readmePath, string $adapterDirectory): ReadmeSitesCheckResult
    {
        try {
            $readme = $this->readFile($readmePath, 'README');
            $manifestPaths = glob(rtrim($adapterDirectory, DIRECTORY_SEPARATOR) . '/*/manifest.json');
            if ($manifestPaths === false || $manifestPaths === []) {
                throw new RuntimeException(sprintf('No adapter manifests found in %s', $adapterDirectory));
            }
            sort($manifestPaths, SORT_STRING);

            $manifests = array_map(fn(string $path): array => $this->readManifest($path), $manifestPaths);
            $readmeNames = $this->supportedSiteNames($readme);
            if ($readmeNames === null) {
                throw new RuntimeException('README supported-sites table not found (expected a "| Site | Slug |" table under "## Supported sites")');
            }
        } catch (JsonException|RuntimeException $exception) {
            return new ReadmeSitesCheckResult(2, [$exception->getMessage()]);
        }

        $matchedManifestIndexes = [];
        $matchedReadmeIndexes = [];
        foreach ([false, true] as $allowAnnotatedSuffix) {
            foreach ($readmeNames as $readmeIndex => $readmeName) {
                if (isset($matchedReadmeIndexes[$readmeIndex])) {
                    continue;
                }

                foreach ($manifests as $manifestIndex => $manifest) {
                    if (isset($matchedManifestIndexes[$manifestIndex])) {
                        continue;
                    }

                    if (! $this->matchesIdentifier($readmeName, $manifest['slug'], $allowAnnotatedSuffix)
                        && ! $this->matchesIdentifier($readmeName, $manifest['name'], $allowAnnotatedSuffix)) {
                        continue;
                    }

                    $matchedManifestIndexes[$manifestIndex] = true;
                    $matchedReadmeIndexes[$readmeIndex] = true;
                    break;
                }
            }
        }

        $messages = [];
        foreach ($manifests as $manifestIndex => $manifest) {
            if (! isset($matchedManifestIndexes[$manifestIndex])) {
                $messages[] = sprintf('MISSING_IN_README %s', $manifest['slug']);
            }
        }

        foreach ($readmeNames as $readmeIndex => $readmeName) {
            if (! isset($matchedReadmeIndexes[$readmeIndex])) {
                $messages[] = sprintf('UNKNOWN_IN_README %s', $readmeName);
            }
        }
        sort($messages, SORT_STRING);

        return new ReadmeSitesCheckResult($messages === [] ? 0 : 1, $messages);
    }

    /**
     * Reads and validates one adapter manifest.
     *
     * @return array{slug: string, name: string}
     */
    private function readManifest(string $path): array
    {
        $manifest = json_decode($this->readFile($path, 'adapter manifest'), true, flags: JSON_THROW_ON_ERROR);
        $slug = is_array($manifest) ? ($manifest['slug'] ?? null) : null;
        $name = is_array($manifest) ? ($manifest['name'] ?? null) : null;
        if (! is_string($slug) || trim($slug) === '' || ! is_string($name) || trim($name) === '') {
            throw new RuntimeException(sprintf('Adapter manifest must contain non-empty slug and name fields: %s', $path));
        }

        return ['slug' => trim($slug), 'name' => trim($name)];
    }

    /** Reads a required file or throws an error suitable for the CLI. */
    private function readFile(string $path, string $description): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(sprintf('Unable to read %s: %s', $description, $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read %s: %s', $description, $path));
        }

        return $contents;
    }

    /**
     * Parses supported-site names, or returns null when the Markdown table is invalid.
     *
     * @return list<string>|null
     */
    private function supportedSiteNames(string $readme): ?array
    {
        $inSection = false;
        $foundHeader = false;
        $foundSeparator = false;
        $headerCellCount = 0;
        $names = [];

        foreach (preg_split('/\R/', $readme) ?: [] as $line) {
            $line = trim($line);
            if (! $inSection) {
                $inSection = preg_match('/^#{1,6}\s+Supported sites\s*$/i', $line) === 1;
                continue;
            }
            if (! $foundHeader && preg_match('/^#{1,6}\s+/', $line) === 1) {
                return null;
            }
            if ($line === '') {
                if ($foundSeparator) {
                    break;
                }
                if ($foundHeader) {
                    return null;
                }
                continue;
            }
            if (! str_starts_with($line, '|')) {
                if ($foundSeparator) {
                    break;
                }
                if ($foundHeader) {
                    return null;
                }
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (! $foundHeader) {
                $foundHeader = isset($cells[1])
                    && strtolower(trim($cells[0], " `\t")) === 'site'
                    && strtolower(trim($cells[1], " `\t")) === 'slug';
                $headerCellCount = count($cells);
                continue;
            }

            if (! $foundSeparator) {
                $foundSeparator = count($cells) === $headerCellCount
                    && array_reduce(
                        $cells,
                        static fn(bool $valid, string $cell): bool => $valid && preg_match('/^:?-{3,}:?$/', $cell) === 1,
                        true,
                    );
                if (! $foundSeparator) {
                    return null;
                }
                continue;
            }

            $name = trim($cells[0], " `\t");
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $foundSeparator ? $names : null;
    }

    /** Compares a README name with a manifest identifier, optionally allowing annotations. */
    private function matchesIdentifier(string $readmeName, string $identifier, bool $allowAnnotatedSuffix = true): bool
    {
        $readmeName = $this->normalize($readmeName);
        $identifier = $this->normalize($identifier);

        return $readmeName === $identifier
            || ($allowAnnotatedSuffix && str_starts_with($readmeName, $identifier . ' ('));
    }

    /** Normalizes case and Markdown code delimiters before comparison. */
    private function normalize(string $name): string
    {
        return strtolower(trim($name, " `\t\r\n"));
    }
}
