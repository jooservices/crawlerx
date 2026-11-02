<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily;

final readonly class SiteConfig
{
    public function __construct(
        public string $baseUrl,
        public string $assetBaseUrl,
        public string $moviePath,
        public string $movieUrlPattern,
        public string $galleryPath,
        public string $galleryImagePath,
        public string $maker,
        public string $label,
        public string $gallerySource,
        public string $movieIdPattern = '#^(\d{6}_\d{3})$#',
        public string $galleryFlagField = 'HasGallery',
    ) {
    }
}
