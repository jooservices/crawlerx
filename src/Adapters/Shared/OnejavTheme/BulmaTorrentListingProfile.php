<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnejavTheme;

final class BulmaTorrentListingProfile
{
    public function __construct(
        public readonly string $siteLabel,
        public readonly string $contextLabel,
        public readonly string $listingLinksSelector,
        public readonly string $itemUrlPattern,
    ) {
    }
}
