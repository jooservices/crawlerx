<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchPlanResolver;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuit;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\Handlers\CurlImpersonateFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PuppeteerStealthFetchHandler;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\AdapterExecutor;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Services\CrawlOrchestrator;
use JOOservices\CrawlerX\Services\CrawlerXService;
use JOOservices\CrawlerX\Services\UrlClassifier;
use Psr\SimpleCache\CacheInterface;

final class CrawlerXFactory
{
    private static ?CrawlOrchestrator $orchestrator = null;

    private static ?FetchFallbackChain $fetchChain = null;

    private static ?LoginCookieProvider $loginCookieProvider = null;

    private static ?CacheInterface $throttleCache = null;

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

    public static function configure(?LoginCookieProvider $logins = null, ?CacheInterface $throttleCache = null): void
    {
        self::$loginCookieProvider = $logins;
        self::$throttleCache = $throttleCache;
        self::$fetchChain = null;
        self::$orchestrator = null;
    }

    public static function reset(): void
    {
        self::$orchestrator = null;
        self::$fetchChain = null;
        self::$loginCookieProvider = null;
        self::$throttleCache = null;
    }

    private static function defaultFetchChain(): FetchFallbackChain
    {
        $runtime = FetchRuntimeConfig::fromEnvironment();
        $runner = new ProcOpenProcessRunner();
        $browserRunner = $runtime->playwrightUrl === null
            ? $runner
            : new BrowserServiceProcessRunner($runtime->playwrightUrl);
        $cookies = new CookieHandoffStore();
        $sessions = new SessionStore();
        $clientFactory = new ClientFactory();

        $playwright = new PlaywrightFamilyFetchHandler($runtime, $browserRunner);

        return new FetchFallbackChain([
            FetchMethod::Http->value => new HttpFetchHandler($clientFactory, $cookies, $sessions, self::$loginCookieProvider),
            FetchMethod::CurlImpersonate->value => new CurlImpersonateFetchHandler($runtime, $runner, $cookies, $sessions, self::$loginCookieProvider),
            FetchMethod::Playwright->value => $playwright,
            FetchMethod::PlaywrightStealth->value => $playwright,
            FetchMethod::ChromeStealth->value => $playwright,
            FetchMethod::PuppeteerStealth->value => new PuppeteerStealthFetchHandler($runtime, $browserRunner),
            FetchMethod::Flaresolverr->value => new FlaresolverrFetchHandler($runtime, null, $sessions, self::$loginCookieProvider),
        ], $cookies, $sessions, self::$loginCookieProvider, $runtime, hostThrottle: new HostThrottle(
            cache: self::$throttleCache,
            nodeId: $runtime->nodeId,
        ), hostCircuit: new HostCircuit(
            cache: self::$throttleCache,
            nodeId: $runtime->nodeId,
        ));
    }
}
