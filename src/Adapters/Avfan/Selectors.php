<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avfan;

final class Selectors
{
    public const LISTING_LINKS = 'a[href^="/en/movies/"]';

    public const PAGINATION_LINKS = 'nav.pagy a[href]';

    public const DETAIL_TITLE = 'h1';

    public const DETAIL_COVER = '.cover img[src]';

    public const DETAIL_DIRECTOR = 'a[href*="/directors/"]';

    public const DETAIL_MAKER = 'a[href*="/makers/"]';

    public const DETAIL_PUBLISHER = 'a[href*="/publishers/"]';

    public const DETAIL_CASTS = 'a[href*="/casts/"]';

    public const DETAIL_TAGS = 'a[href*="/tags"]';

    public const DETAIL_SCREENSHOTS = 'a[data-fancybox="gallery"][href*="/samples/"]';

    public const DETAIL_MAGNETS = '#magnets .magnet-list-item';
}
