<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\MovieRowFields as SharedMovieRowFields;

final class MovieRowFields extends SharedMovieRowFields
{
    public function __construct()
    {
        parent::__construct(Site::config());
    }
}
