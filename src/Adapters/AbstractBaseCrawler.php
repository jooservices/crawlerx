<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\SiteAdapter;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\AdapterManifestDto;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Http\SeededCrawlHttpClient;
use JOOservices\CrawlerX\Services\ClientFactory;
use RuntimeException;

abstract class AbstractBaseCrawler implements DetailCapable, ListingCapable, SiteAdapter
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    protected CrawlHttpClient $client;

    public function __construct(
        private readonly ClientFactory $clientFactory,
        protected readonly ?AdapterManifestDto $manifest = null,
    ) {
        $this->options = array_merge($this->resolveDefaultOptions($this->manifest), $this->options);
        $this->client = $this->clientFactory->factory($this->options);
    }

    public function prepareRequest(CrawlRequestDto $request): void
    {
        $this->refreshClient($request);
    }

    protected function refreshClient(CrawlRequestDto $request): void
    {
        $http = [];
        $httpOptions = $request->options?->http;
        if ($httpOptions !== null) {
            if ($httpOptions->timeout !== null) {
                $http['timeout'] = $httpOptions->timeout;
            }

            if ($httpOptions->verifySsl !== null) {
                $http['verify_ssl'] = $httpOptions->verifySsl;
            }

            if ($httpOptions->headers !== null) {
                $http['headers'] = $httpOptions->headers;
            }
        }

        $inner = $this->clientFactory->factory(array_merge($this->options, $http));
        $this->client = $request->fetch !== null
            ? new SeededCrawlHttpClient($inner, $request->fetch)
            : $inner;
    }

    abstract public function listing(CrawlRequestDto $request): CrawlListResultDto;

    abstract public function detail(CrawlRequestDto $request): CrawlItemResultDto;

    /**
     * @return list<array{match: string|callable(CrawlRequestDto): bool, type: class-string<TypeInterface>}>
     */
    protected function listingRoutes(): array
    {
        return [];
    }

    /**
     * @return list<array{match: string|callable(CrawlRequestDto): bool, type: class-string<TypeInterface>}>
     */
    protected function detailRoutes(): array
    {
        return [];
    }

    /**
     * @return class-string<TypeInterface>
     */
    protected function defaultListingType(): string
    {
        throw new RuntimeException(static::class . ' must define listingRoutes() or override defaultListingType().');
    }

    /**
     * @return class-string<TypeInterface>
     */
    protected function defaultDetailType(): string
    {
        throw new RuntimeException(static::class . ' must define detailRoutes() or override defaultDetailType().');
    }

    /**
     * @return class-string<TypeInterface>
     */
    protected function resolveListingType(CrawlRequestDto $request): string
    {
        foreach ($this->listingRoutes() as $route) {
            if ($this->routeMatches($route['match'], $request)) {
                return $route['type'];
            }
        }

        return $this->defaultListingType();
    }

    /**
     * @return class-string<TypeInterface>
     */
    protected function resolveDetailType(CrawlRequestDto $request): string
    {
        foreach ($this->detailRoutes() as $route) {
            if ($this->routeMatches($route['match'], $request)) {
                return $route['type'];
            }
        }

        return $this->defaultDetailType();
    }

    /**
     * @param  string|callable(CrawlRequestDto): bool  $match
     */
    protected function routeMatches(string|callable $match, CrawlRequestDto $request): bool
    {
        if (is_callable($match)) {
            $matched = $match($request);

            return $matched === true;
        }

        return str_contains($request->url, $match);
    }

    /**
     * @param  class-string<TypeInterface>  $typeClass
     */
    protected function makeType(string $typeClass): TypeInterface
    {
        /** @var TypeInterface $type */
        $type = new $typeClass($this->client);

        return $type;
    }

    protected function manifest(): AdapterManifestDto
    {
        if ($this->manifest === null) {
            throw new RuntimeException(static::class . ' requires an adapter manifest.');
        }

        return $this->manifest;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveDefaultOptions(?AdapterManifestDto $manifest): array
    {
        if ($manifest === null) {
            return [];
        }

        $options = [];

        if ($manifest->baseUrl !== '') {
            $options['base_uri'] = $manifest->baseUrl;
        }

        if ($manifest->browserHeaders !== null && $manifest->browserHeaders !== []) {
            $options['headers'] = $manifest->browserHeaders;
        }

        $timeout = $manifest->defaultCrawlConfig['timeout'] ?? null;
        if (is_int($timeout)) {
            $options['timeout'] = $timeout;
        } elseif (is_numeric($timeout) && (int) $timeout > 0) {
            $options['timeout'] = (int) $timeout;
        }

        return $options;
    }
}
