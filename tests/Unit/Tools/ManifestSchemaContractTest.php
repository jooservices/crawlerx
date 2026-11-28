<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Tests\Support\JsonSchemaSubset;
use JOOservices\CrawlerX\Tools\ManifestConsistency\ManifestChecker;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use stdClass;

require_once dirname(__DIR__, 3) . '/tools/ManifestChecker.php';

/** The manifest contract (docs/manifest.schema.json) holds for every adapter manifest. */
final class ManifestSchemaContractTest extends TestCase
{
    private const SCHEMA = __DIR__ . '/../../../docs/manifest.schema.json';

    private const ADAPTERS = __DIR__ . '/../../../src/Adapters';

    public function test_every_adapter_manifest_satisfies_the_schema(): void
    {
        $violations = [];
        foreach ($this->manifestFiles() as $file) {
            $manifest = $this->decode((string) file_get_contents($file));
            foreach ($this->validator()->validate($manifest) as $violation) {
                $violations[] = sprintf('adapter %s (%s) violates the manifest schema at %s', $this->slug($manifest, $file), basename(dirname($file)), $violation);
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function test_there_is_a_manifest_to_check(): void
    {
        self::assertNotSame([], $this->manifestFiles());
    }

    public function test_a_broken_manifest_is_reported_with_its_path_and_the_rule(): void
    {
        $manifest = $this->decode((string) file_get_contents(self::ADAPTERS . '/Muramura/manifest.json'));
        $manifest->adapter->capabilities = ['listing', 'teleport'];
        $manifest->slug = 'Bad Slug';
        unset($manifest->runtime->fetch);

        $violations = $this->validator()->validate($manifest);

        self::assertContains('/slug: does not match the pattern ^[a-z0-9][a-z0-9_-]*$', $violations);
        self::assertContains('/adapter/capabilities/1: value is not one of the allowed values', $violations);
        self::assertContains('/runtime: missing required key fetch', $violations);
    }

    public function test_the_required_keys_agree_with_the_manifest_checker(): void
    {
        $schema = $this->decode((string) file_get_contents(self::SCHEMA));
        $checker = (new ReflectionClassConstant(ManifestChecker::class, 'REQUIRED_KEYS'))->getValue();

        self::assertEqualsCanonicalizing($checker, $schema->required);
    }

    private function validator(): JsonSchemaSubset
    {
        return new JsonSchemaSubset($this->decode((string) file_get_contents(self::SCHEMA)));
    }

    /** @return list<string> */
    private function manifestFiles(): array
    {
        $files = glob(self::ADAPTERS . '/*/manifest.json');
        self::assertIsArray($files);
        sort($files, SORT_STRING);

        return $files;
    }

    private function decode(string $json): stdClass
    {
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $decoded);

        return $decoded;
    }

    private function slug(stdClass $manifest, string $file): string
    {
        return is_string($manifest->slug ?? null) ? $manifest->slug : basename(dirname($file));
    }
}
