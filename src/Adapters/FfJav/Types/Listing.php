<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\FfJav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\FfJav\Selectors;
use JOOservices\CrawlerX\Adapters\FfJav\UrlNormalizer;
use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\BulmaTorrentListing;
use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\BulmaTorrentListingProfile;
use JOOservices\CrawlerX\Contracts\TypeInterface;

final class Listing extends BulmaTorrentListing implements TypeInterface
{
    public function __construct(
        CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
        parent::__construct(
            $client,
            new BulmaTorrentListingProfile(
                siteLabel: 'ffjav',
                contextLabel: 'listing',
                listingLinksSelector: Selectors::LISTING_LINKS,
                itemUrlPattern: Selectors::ITEM_URL_PATTERN,
            ),
            fn(string $base, string $href): string => $this->normalizer->absolute($base, $href),
        );
    }
}
