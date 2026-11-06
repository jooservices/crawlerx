<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms;

use Symfony\Component\DomCrawler\UriResolver;

final readonly class SiteConfig
{
    public function __construct(
        public string $baseUrl,
        public string $maker,
        public string $datePath = '/works/date',
        public string $listingPath = '/works/list/date/',
        public string $groupListPath = '/works/list/',
        public string $detailPath = '/works/detail/',
        public string $actressPath = '/actress/detail/',
        public int $dateGapSeconds = 5,
        public string $searchPath = '/search/list',
        public bool $plainPerformers = false,
    ) {
    }

    public function absolute(string $url): string
    {
        return UriResolver::resolve($url, $this->baseUrl . '/');
    }

    public function entityId(string $url, string $prefix): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, $prefix)) {
            throw new \InvalidArgumentException('Unrecognized Maker CMS entity URL.');
        }

        $id = trim(substr($path, strlen($prefix)), '/');
        if ($id === '' || str_contains($id, '/')) {
            throw new \InvalidArgumentException('Missing Maker CMS entity ID.');
        }

        return $id;
    }
}
