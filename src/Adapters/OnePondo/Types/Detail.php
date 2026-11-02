<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo\Types;

use JOOservices\CrawlerX\Adapters\OnePondo\MovieRowFields;
use JOOservices\CrawlerX\Adapters\OnePondo\Site;
use JOOservices\CrawlerX\Adapters\OnePondo\UrlNormalizer;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Detail as SharedDetail;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;

final class Detail extends SharedDetail
{
    public function __construct(
        CrawlHttpClient $client,
        UrlNormalizer $normalizer = new UrlNormalizer(),
        MovieRowFields $fields = new MovieRowFields(),
    ) {
        parent::__construct($client, $normalizer, $fields, Site::config());
    }
}
