<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

use JOOservices\CrawlerX\Dto\AdapterManifestDto;

interface AdapterManifestRegistry
{
    /** @return array<string, AdapterManifestDto> */
    public function all(): array;

    public function get(string $slug): ?AdapterManifestDto;

    public function has(string $slug): bool;

    /** @return array<string, string> */
    public function adapterMap(): array;

    /** @return list<string> */
    public function slugs(): array;
}
