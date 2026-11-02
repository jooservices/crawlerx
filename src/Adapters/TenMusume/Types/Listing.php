<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TenMusume\Types;

use JOOservices\CrawlerX\Adapters\TenMusume\MovieRowFields;
use JOOservices\CrawlerX\Adapters\TenMusume\Site;
use JOOservices\CrawlerX\Adapters\TenMusume\UrlNormalizer;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Listing as SharedListing;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;

final class Listing extends SharedListing
{
    public function __construct(
        CrawlHttpClient $client,
        UrlNormalizer $normalizer = new UrlNormalizer(),
        MovieRowFields $fields = new MovieRowFields(),
    ) {
        parent::__construct($client, $normalizer, $fields, Site::config());
    }
}
