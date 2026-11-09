<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\Cache\ResponseCache;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchPlanResolver;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuit;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
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
use Throwable;

final class CrawlerXFactory
{
    private static ?CrawlOrchestrator $orchestrator = null;

    private static ?FetchFallbackChain $fetchChain = null;

    private static ?LoginCookieProvider $loginCookieProvider = null;

    private static ?CacheInterface $throttleCache = null;

    private static ?CacheInterface $responseCache = null;

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

    public static function configure(
        ?LoginCookieProvider $logins = null,
        ?CacheInterface $throttleCache = null,
        ?CacheInterface $responseCache = null,
    ): void {
        self::$loginCookieProvider = $logins;
        self::$throttleCache = $throttleCache;
        self::$responseCache = $responseCache;
        self::$fetchChain = null;
        self::$orchestrator = null;
    }

    public static function reset(): void
    {
        self::$orchestrator = null;
        self::$fetchChain = null;
        self::$loginCookieProvider = null;
        self::$throttleCache = null;
        self::$responseCache = null;
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
        $http = new HttpFetchHandler($clientFactory, $cookies, $sessions, self::$loginCookieProvider);
        $httpHandler = self::$responseCache !== null && $runtime->responseCacheTtlSeconds > 0
            ? new ResponseCache($http, self::$responseCache, $runtime->responseCacheTtlSeconds)
            : $http;
        $robotsGuard = new RobotsGuard(
            fetchRobotsTxt: static function (string $url) use ($clientFactory, $runtime): ?string {
                // A faked client serves fixtures and records every request; there is no robots.txt
                // fixture, so skip the fetch and treat the host as unrestricted (fail-open).
                if (ClientBuilder::isFaked()) {
                    return '';
                }

                try {
                    $response = $clientFactory->factory([
                        'timeout' => 10,
                        'headers' => $runtime->userAgent !== null && $runtime->userAgent !== ''
                            ? ['User-Agent' => $runtime->userAgent]
                            : [],
                    ])->get($url);
                    $psr = $response->toPsrResponse();
                    $status = $psr->getStatusCode();

                    if ($status === 404) {
                        return '';
                    }

                    return $status >= 200 && $status < 300 ? (string) $psr->getBody() : null;
                } catch (Throwable) {
                    return null;
                }
            },
            cache: self::$throttleCache,
            logger: static function (string $message): void {
                error_log($message);
            },
        );

        return new FetchFallbackChain([
            FetchMethod::Http->value => $httpHandler,
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
        ), robotsGuard: $robotsGuard);
    }
}
