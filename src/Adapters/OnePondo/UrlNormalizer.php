<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\UrlNormalizer as SharedUrlNormalizer;

final class UrlNormalizer extends SharedUrlNormalizer
{
    public function __construct()
    {
        parent::__construct(Site::config());
    }
}
