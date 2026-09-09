<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Registry;

use JOOservices\CrawlerX\Contracts\AdapterManifestRegistry;
use JOOservices\CrawlerX\Contracts\SiteAdapter;
use JOOservices\CrawlerX\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerX\Services\ClientFactory;

final class AdapterRegistry
{
    /** @var array<string, class-string<SiteAdapter>> */
    private array $map = [];

    public function __construct(
        private readonly ClientFactory $clientFactory = new ClientFactory(),
        private readonly ?AdapterManifestRegistry $manifests = null,
    ) {
    }

    /**
     * @param  class-string<SiteAdapter>  $crawlerClass
     */
    public function register(string $name, string $crawlerClass): void
    {
        $this->map[$name] = $crawlerClass;
    }

    public function registerFromManifests(AdapterManifestRegistry $manifests): void
    {
        foreach ($manifests->adapterMap() as $slug => $class) {
            if (! is_a($class, SiteAdapter::class, true)) {
                throw new AdapterNotFoundException($slug);
            }

            $this->register($slug, $class);
        }
    }

    public function resolve(string $name): SiteAdapter
    {
        if (! isset($this->map[$name])) {
            throw new AdapterNotFoundException($name);
        }

        $manifest = $this->manifests?->get($name);
        $class = $this->map[$name];
        /** @var SiteAdapter $adapter */
        $adapter = new $class($this->clientFactory, $manifest);

        return $adapter;
    }

    /** @return array<string, class-string<SiteAdapter>> */
    public function all(): array
    {
        return $this->map;
    }
}
