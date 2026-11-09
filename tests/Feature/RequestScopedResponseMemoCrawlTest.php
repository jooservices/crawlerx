<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchPlanResolver;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Services\CrawlOrchestrator;
use JOOservices\CrawlerX\Services\CrawlerXService;
use JOOservices\CrawlerX\Services\UrlClassifier;
use JOOservices\CrawlerX\Tools\Bench\RepeatedFollowUpCrawler;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/bench/RepeatedFollowUpCrawler.php';

final class RequestScopedResponseMemoCrawlTest extends TestCase
{
    protected function tearDown(): void
    {
        ClientBuilder::clearFake();
        RepeatedFollowUpCrawler::$followUpUrl = '';
        RepeatedFollowUpCrawler::$memoEnabled = true;
        parent::tearDown();
    }

    public function test_fixture_crawl_reuses_a_repeated_follow_up_request(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(
            (string) file_get_contents($root . '/src/Adapters/Smartflash/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $sample = null;
        foreach ($manifest['runtime']['fixtureSamples'] as $candidate) {
            if (($candidate['type'] ?? null) === 'gallery' && isset($candidate['followUps'][0])) {
                $sample = $candidate;
                break;
            }
        }

        self::assertIsArray($sample);
        $followUp = $sample['followUps'][0];
        self::assertIsString($sample['url'] ?? null);
        self::assertIsString($sample['name'] ?? null);
        self::assertIsString($followUp['url'] ?? null);
        self::assertIsString($followUp['name'] ?? null);

        $fixtureDirectory = $root . '/tests/Fixtures/smartflash/';
        $rootBody = (string) file_get_contents($fixtureDirectory . $sample['name']);
        $followUpBody = (string) file_get_contents($fixtureDirectory . $followUp['name']);
        RepeatedFollowUpCrawler::$followUpUrl = $followUp['url'];

        ClientBuilder::fake();
        ClientBuilder::respond('GET', $sample['url'], (new TestResponseSequence())
            ->push(TestResponse::make(200, [], $rootBody)));
        ClientBuilder::respond('GET', $followUp['url'], (new TestResponseSequence())
            ->push(TestResponse::make(200, [], $followUpBody))
            ->push(TestResponse::make(200, [], 'unexpected second follow-up response')));

        $manifests = new FileAdapterManifestRegistry();
        $clients = new ClientFactory();
        $adapters = new AdapterRegistry($clients, $manifests);
        $adapters->register('smartflash', RepeatedFollowUpCrawler::class);
        $fetchChain = new FetchFallbackChain(
            [FetchMethod::Http->value => new HttpFetchHandler($clients, new CookieHandoffStore())],
            hostThrottle: new HostThrottle(sleeper: static function (int $microseconds): void {
            }),
            maxTransientRetries: 0,
        );
        $orchestrator = new CrawlOrchestrator(
            new UrlClassifier($adapters),
            new CrawlerXService($adapters, new AdapterExecutor()),
            $manifests,
            new FetchPlanResolver(),
            $fetchChain,
        );

        $result = $orchestrator->url($sample['url'])
            ->site('smartflash')
            ->type(CrawlType::Gallery)
            ->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame([$followUpBody, $followUpBody], $result->meta['follow_up_bodies']);
        self::assertSame(
            [$sample['url'], $followUp['url']],
            array_map(static fn(object $record): string => (string) $record->request->getUri(), ClientBuilder::recorded()),
        );
    }
}
