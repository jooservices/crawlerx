<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

final class Selectors
{
    public const MOVIE_URL_PATTERN = '#^https://(?:en|www)\.1pondo\.tv/movies/(\d{6}_\d{3})/?$#i';

    public const MOVIE_ID_PATTERN = '#^(\d{6}_\d{3})$#';
}
