<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OneFourOneJav;

final class Selectors
{
    public const LISTING_LINKS = 'a[href]';

    public const ITEM_URL_PATTERN = '#^https://(?:www\.)?141jav\.com/torrent/[^/]+$#i';

    public const DETAIL_TITLE = 'h5.title a';

    public const DETAIL_COVER = '.card img.image';

    public const DETAIL_TAGS = 'a.tag.is-light';

    public const DETAIL_DATE = 'p.subtitle a';

    public const DETAIL_PERFORMERS = 'a.panel-block[href*="/actress/"]';

    public const DETAIL_DESCRIPTION = 'p.level.has-text-grey-dark';

    public const DETAIL_DOWNLOAD = 'a[href$=".torrent"], a[href*=".torrent?"]';
}
