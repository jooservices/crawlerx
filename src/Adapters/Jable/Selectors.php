<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jable;

final class Selectors
{
    public const LISTING_CARDS = '.video-img-box';

    public const LISTING_LINKS = '.video-img-box a[href*="/videos/"]';

    public const LISTING_TITLE = 'h6.title a';

    public const LISTING_COVER = '.video-img-box img';

    public const LISTING_DURATION = '.absolute-bottom-right .label';

    public const LISTING_STATS = '.sub-title';

    public const PAGINATION_LINKS = 'ul.pagination a.page-link';

    public const DETAIL_TITLE = 'section.video-info h4';

    public const DETAIL_DESCRIPTION = 'section.video-info h5.desc';

    public const DETAIL_COVER = 'meta[property="og:image"]';

    public const DETAIL_KEYWORDS = 'meta[name="keywords"]';

    public const DETAIL_MODELS = '.models a.model';

    public const DETAIL_TAG_CATEGORIES = 'h5.tags a.cat';

    public const DETAIL_TAG_LINKS = 'h5.tags a:not(.cat)';

    public const DETAIL_HEADER_STATS = '.video-info .info-header h6';
}
