<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;
use JOOservices\CrawlerX\Tests\Support\ManifestFixtureCatalog;
use PHPUnit\Framework\Attributes\DataProvider;

final class AllAdaptersCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{FixtureSampleDto, string}>
     */
    public static function manifestSamplesProvider(): iterable
    {
        foreach (ManifestFixtureCatalog::executableSamples() as $entry) {
            $key = $entry['slug'] . ':' . $entry['sample']->type . ':' . $entry['sample']->name;

            yield $key => [
                $entry['sample'],
                ManifestFixtureCatalog::fixtureRelativePath($entry['slug'], $entry['sample']->name),
            ];
        }
    }

    #[DataProvider('manifestSamplesProvider')]
    public function test_crawlerx_crawls_manifest_fixture(FixtureSampleDto $sample, string $fixtureRelativePath): void
    {
        $fullPath = __DIR__ . '/../Fixtures/' . $fixtureRelativePath;
        self::assertFileExists($fullPath);
        $html = (string) file_get_contents($fullPath);
        $blocked = $this->isBlockedCapture($html);

        FixtureResponder::for('GET', $sample->url)->file($fixtureRelativePath, $blocked ? 403 : 200);

        if ($blocked) {
            try {
                CrawlerX::url($sample->url)->crawl();
                self::fail('Expected blocked capture to throw.');
            } catch (CrawlBlockedException|CrawlParseException) {
                self::assertTrue(true);
            }

            return;
        }

        $result = CrawlerX::url($sample->url)->crawl();

        if (str_contains($sample->type, 'listing')) {
            self::assertInstanceOf(CrawlListResultDto::class, $result);
            self::assertNotEmpty($result->items);

            return;
        }

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        $entity = $result->meta[$result->entityType] ?? null;
        self::assertIsArray($entity);
        self::assertNotSame('', trim((string) ($entity['title'] ?? $entity['name'] ?? '')));
    }

    private function isBlockedCapture(string $html): bool
    {
        return str_contains($html, '<title>Just a moment')
            || str_contains($html, 'Performing security verification')
            || str_contains($html, 'driver-verify')
            || str_contains($html, 'Age Verification');
    }
}
