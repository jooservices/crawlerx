<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Onejav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\Onejav\Selectors;
use JOOservices\CrawlerX\Adapters\Onejav\UrlNormalizer;
use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\BulmaTorrentListing;
use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\BulmaTorrentListingProfile;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Support\QueryPageUrl;

final class Listing extends BulmaTorrentListing implements TypeInterface
{
    public function __construct(
        CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
        parent::__construct(
            $client,
            new BulmaTorrentListingProfile(
                siteLabel: 'Onejav',
                contextLabel: 'listing',
                listingLinksSelector: Selectors::LISTING_LINKS,
                itemUrlPattern: Selectors::ITEM_URL_PATTERN,
            ),
            fn(string $base, string $href): string => $this->normalizer->absolute($base, $href),
            fn(string $url, int $page): string => QueryPageUrl::build($url, $page),
        );
    }
}
