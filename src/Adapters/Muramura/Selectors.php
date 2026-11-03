<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muramura;

final class Selectors
{
    public const MOVIE_ID_PATTERN = '#^(\d{6}_\d{2,4})$#';

    public const MOVIE_URL_PATTERN = '#^https?://(?:www\.)?muramura\.tv/movies/(\d{6}_\d{2,4})/?$#i';
}
