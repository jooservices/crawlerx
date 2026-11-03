<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TenMusume;

final class Selectors
{
    public const MOVIE_URL_PATTERN = '#^https://(?:www\.)?10musume\.com/movies/(\d{6}_\d{2})/?$#i';

    public const MOVIE_ID_PATTERN = '#^(\d{6}_\d{2})$#';
}
