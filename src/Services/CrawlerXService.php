<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Registry\AdapterRegistry;

final class CrawlerXService
{
    private string $currentSite = '';

    public function __construct(
        private readonly AdapterRegistry $registry,
        private readonly AdapterExecutor $executor,
    ) {
    }

    public function site(string $name): static
    {
        $service = clone $this;
        $service->currentSite = $name;

        return $service;
    }

    public function crawl(CrawlRequestDto $request): CrawlListResultDto|CrawlItemResultDto
    {
        $adapter = $this->registry->resolve($this->currentSite);

        /** @var CrawlListResultDto|CrawlItemResultDto $result */
        $result = $this->executor->execute($adapter, $request);

        return $result;
    }
}
