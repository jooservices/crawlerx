<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools;

use JsonException;
use RuntimeException;

final readonly class ReadmeSitesCheckResult
{
    /** @param list<string> $messages */
    public function __construct(
        public int $exitCode,
        public array $messages,
    ) {
    }
}

final class ReadmeSitesChecker
{
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

        $knownNames = [];
        foreach ($manifests as $manifest) {
            $knownNames[] = $manifest['slug'];
            $knownNames[] = $manifest['name'];
        }

        $messages = [];
        foreach ($manifests as $manifest) {
            if (! $this->containsReadmeName($readmeNames, $manifest['slug']) && ! $this->containsReadmeName($readmeNames, $manifest['name'])) {
                $messages[] = sprintf('MISSING_IN_README %s', $manifest['slug']);
            }
        }

        foreach ($readmeNames as $readmeName) {
            if (! $this->matchesKnownName($readmeName, $knownNames)) {
                $messages[] = sprintf('UNKNOWN_IN_README %s', $readmeName);
            }
        }
        sort($messages, SORT_STRING);

        return new ReadmeSitesCheckResult($messages === [] ? 0 : 1, $messages);
    }

    /** @return array{slug: string, name: string} */
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

    /** @return list<string>|null */
    private function supportedSiteNames(string $readme): ?array
    {
        $inSection = false;
        $foundHeader = false;
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
                if ($foundHeader) {
                    break;
                }
                continue;
            }
            if (! str_starts_with($line, '|')) {
                if ($foundHeader) {
                    break;
                }
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (! $foundHeader) {
                $foundHeader = isset($cells[1])
                    && strtolower(trim($cells[0], " `\t")) === 'site'
                    && strtolower(trim($cells[1], " `\t")) === 'slug';
                continue;
            }
            if (preg_match('/^:?-{3,}:?$/', $cells[0]) === 1) {
                continue;
            }

            $name = trim($cells[0], " `\t");
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $foundHeader ? $names : null;
    }

    /** @param list<string> $readmeNames */
    private function containsReadmeName(array $readmeNames, string $identifier): bool
    {
        foreach ($readmeNames as $readmeName) {
            if ($this->matchesIdentifier($readmeName, $identifier)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $knownNames */
    private function matchesKnownName(string $readmeName, array $knownNames): bool
    {
        foreach ($knownNames as $knownName) {
            if ($this->matchesIdentifier($readmeName, $knownName)) {
                return true;
            }
        }

        return false;
    }

    private function matchesIdentifier(string $readmeName, string $identifier): bool
    {
        $readmeName = $this->normalize($readmeName);
        $identifier = $this->normalize($identifier);

        return $readmeName === $identifier || str_starts_with($readmeName, $identifier . ' (');
    }

    private function normalize(string $name): string
    {
        return strtolower(trim($name, " `\t\r\n"));
    }
}
