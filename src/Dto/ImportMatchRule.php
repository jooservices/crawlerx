<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use Closure;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

final readonly class ImportMatchRule
{
    /**
     * @param  Closure(string, string, string): bool  $matcher
     */
    public function __construct(
        public ImportEntity $entity,
        public string $urlType,
        public CrawlType $crawlType,
        public int $priority,
        private Closure $matcher,
        public bool $rejectListing = false,
    ) {
    }

    public function matches(string $url, string $path, string $query): bool
    {
        return ($this->matcher)($url, $path, $query);
    }
}
