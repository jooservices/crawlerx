<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Missav;

final class Selectors
{
    public const LISTING_LINKS = 'a[href]';

    public const PAGINATION_LINKS = 'a[href]';

    public const DETAIL_TITLE = 'h1, h2, meta[property="og:title"], meta[name="title"]';

    public const DETAIL_COVER = 'meta[property="og:image"], video[poster], img[src]';

    public const DETAIL_DESCRIPTION = 'meta[property="og:description"], meta[name="description"], .description, [data-description]';

    public const DETAIL_DATE = 'meta[property="og:video:release_date"], meta[property="video:release_date"], meta[itemprop="uploadDate"], time[datetime], [data-release-date], .release-date, .date';

    public const DETAIL_DURATION = 'meta[property="og:video:duration"], meta[property="video:duration"], meta[itemprop="duration"], [data-duration], .duration';

    public const DETAIL_TAGS = 'a[href*="/genres/"], a[href*="/genre/"], a[href*="/tags/"], a[href*="/tag/"]';

    public const DETAIL_PERFORMERS = 'a[href*="/actresses/"], a[href*="/actress/"], a[href*="/makers/"], a[href*="/maker/"]';
}
