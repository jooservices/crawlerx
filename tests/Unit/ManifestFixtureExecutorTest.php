<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;
use JOOservices\CrawlerX\Tests\Support\ManifestFixtureCatalog;
use PHPUnit\Framework\Attributes\DataProvider;

final class ManifestFixtureExecutorTest extends CrawlerXTestCase
{
    /**
     * @return iterable<string, array{string, FixtureSampleDto, string}>
     */
    public static function manifestSamplesProvider(): iterable
    {
        foreach (ManifestFixtureCatalog::executableSamples() as $entry) {
            $key = $entry['slug'] . ':' . $entry['sample']->type . ':' . $entry['sample']->name;

            yield $key => [
                $entry['slug'],
                $entry['sample'],
                ManifestFixtureCatalog::fixtureRelativePath($entry['slug'], $entry['sample']->name),
            ];
        }
    }

    #[DataProvider('manifestSamplesProvider')]
    public function test_adapter_executor_crawls_manifest_fixture(
        string $slug,
        FixtureSampleDto $sample,
        string $fixtureRelativePath,
    ): void {
        $fullPath = __DIR__ . '/../Fixtures/' . $fixtureRelativePath;
        $html = (string) file_get_contents($fullPath);
        $blocked = str_contains($html, '<title>Just a moment')
            || str_contains($html, 'Performing security verification')
            || str_contains($html, 'driver-verify')
            || str_contains($html, 'Age Verification');

        if ($slug === 's1' && $sample->name === 'dates.html') {
            FixtureResponder::for('GET', 'https://s1s1s1.com/works/list/date/2026-11-24')->file('s1/list-2026-11-24.html');
        }
        if ($slug === 'ideapocket' && $sample->name === 'dates.html') {
            FixtureResponder::for('GET', 'https://ideapocket.com/works/list/date/2026-12-01')->file('ideapocket/list-2026-12-01.html');
        }
        if ($slug === 'premiumbeauty' && $sample->name === 'dates.html') {
            FixtureResponder::for('GET', 'https://premium-beauty.com/works/list/date/2026-11-17')->file('premiumbeauty/list-2026-11-17.html');
        }
        if ($slug === 'attackers' && $sample->name === 'dates.html') {
            FixtureResponder::for('GET', 'https://attackers.net/works/list/date/2026-12-01')->file('attackers/list-2026-12-01.html');
        }

        if ($slug === 'moodyz' && $sample->name === 'dates.html') {
            FixtureResponder::for('GET', 'https://moodyz.com/works/list/date/2026-12-01')->file('moodyz/list-2026-12-01.html');
        }

        if ($slug === 'madonna' && $sample->name === 'dates.html') {
            FixtureResponder::for('GET', 'https://madonna-av.com/works/list/date/2026-12-01')->file('madonna/list-2026-12-01.html');
        }

        FixtureResponder::for('GET', $sample->url)->file($fixtureRelativePath, $blocked ? 403 : 200);
        ManifestFixtureCatalog::registerSupportingResponses($slug);

        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);

        $adapter = $registry->resolve($slug);
        $type = ManifestFixtureCatalog::crawlTypeFromSample($sample);
        $request = new CrawlRequestDto(url: $sample->url, type: $type, page: 1);

        if ($blocked) {
            try {
                (new AdapterExecutor())->execute($adapter, $request);
                self::fail('Expected blocked capture to throw.');
            } catch (CrawlBlockedException|CrawlParseException) {
                self::assertTrue(true);
            }

            return;
        }

        $result = (new AdapterExecutor())->execute($adapter, $request);

        if ($type->value === 'listing' || str_contains($type->value, 'listing')) {
            self::assertInstanceOf(CrawlListResultDto::class, $result);
            self::assertNotEmpty($result->items);

            return;
        }

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        $entity = $result->meta[$result->entityType] ?? null;
        self::assertIsArray($entity);
        self::assertNotSame('', trim((string) ($entity['title'] ?? $entity['name'] ?? '')));
    }
}
