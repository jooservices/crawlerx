<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchPlanResolver;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\CurlImpersonateFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PuppeteerStealthFetchHandler;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Services\CrawlOrchestrator;
use JOOservices\CrawlerX\Services\CrawlerXService;
use JOOservices\CrawlerX\Services\UrlClassifier;

final class CrawlerXFactory
{
    private static ?CrawlOrchestrator $orchestrator = null;

    private static ?FetchFallbackChain $fetchChain = null;

    public static function create(): CrawlOrchestrator
    {
        if (self::$orchestrator instanceof CrawlOrchestrator) {
            return self::$orchestrator;
        }

        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);

        $service = new CrawlerXService($registry, new AdapterExecutor());
        self::$orchestrator = new CrawlOrchestrator(
            new UrlClassifier($registry),
            $service,
            $manifests,
            new FetchPlanResolver(),
            self::$fetchChain ?? self::defaultFetchChain(),
        );

        return self::$orchestrator;
    }

    public static function useFetchChain(FetchFallbackChain $chain): void
    {
        self::$fetchChain = $chain;
        self::$orchestrator = null;
    }

    public static function reset(): void
    {
        self::$orchestrator = null;
        self::$fetchChain = null;
    }

    private static function defaultFetchChain(): FetchFallbackChain
    {
        $runtime = FetchRuntimeConfig::fromEnvironment();
        $runner = new ProcOpenProcessRunner();
        $browserRunner = $runtime->browserServiceUrl === null
            ? $runner
            : new BrowserServiceProcessRunner($runtime->browserServiceUrl);
        $cookies = new CookieHandoffStore();
        $clientFactory = new ClientFactory();

        $playwright = new PlaywrightFamilyFetchHandler($runtime, $browserRunner);

        return new FetchFallbackChain([
            FetchMethod::Http->value => new HttpFetchHandler($clientFactory, $cookies),
            FetchMethod::CurlImpersonate->value => new CurlImpersonateFetchHandler($runtime, $runner, $cookies),
            FetchMethod::Playwright->value => $playwright,
            FetchMethod::PlaywrightStealth->value => $playwright,
            FetchMethod::ChromeStealth->value => $playwright,
            FetchMethod::PuppeteerStealth->value => new PuppeteerStealthFetchHandler($runtime, $browserRunner),
            FetchMethod::Flaresolverr->value => new FlaresolverrFetchHandler($runtime),
        ], $cookies);
    }
}
