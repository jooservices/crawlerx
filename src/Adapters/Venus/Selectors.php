<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Venus;

final class Selectors
{
    public const LISTING_CARDS = '.topNewreleaseList > .hero > ul > li';

    public const CARD_TITLE = '.topNewreleaseListTtl';

    public const CARD_PERFORMERS = '.topNewreleaseListName > div';

    public const CARD_DATE = '.topNewreleaseListDetail > p:last-child';

    public const CARD_COVER = '.topNewreleaseListImg img';

    public const PAGER_CURRENT = '.wp-pagenavi .current';

    public const PAGER_NEXT = '.wp-pagenavi .nextpostslink';

    public const PAGER_PAGES = '.wp-pagenavi a.page[href]';

    public const PAGER_LAST = '.wp-pagenavi .last';

    public const DETAIL_TITLE = '.products h1';

    public const DETAIL_COVER_LINK = '.productsImg a[href]';

    public const DETAIL_COVER_IMAGE = '.productsImg img';

    public const DETAIL_ROWS = '.productsDataDetail dl';
}
