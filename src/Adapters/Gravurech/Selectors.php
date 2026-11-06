<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravurech;

final class Selectors
{
    public const LISTING_CARDS = '.products__list > .product';

    public const CARD_LINK = '.thumbnail a[href]';

    public const CARD_IMAGE = '.thumbnail img';

    public const CARD_TITLE = 'p.title';

    public const CARD_CATEGORY = '.tags .tag--category';

    public const PAGINATION_CURRENT = '.pagination li.current';

    public const PAGINATION_PAGES = '.pagination li.num';

    public const PAGINATION_NEXT = '.pagination li.next a[rel="next"]';

    public const DETAIL_ROOT = '.product-detail';

    public const DETAIL_TITLE = '.product-detail h1.title';

    public const DETAIL_CATEGORY = '.product-detail .detail .tags .tag--category';

    public const DETAIL_PERFORMERS = '.product-detail .detail .person a[href]';

    public const DETAIL_TAGS = '.product-detail .detail ul.hashtag a[href]';

    public const DETAIL_DESCRIPTION = '.product-detail .detail p.description';

    public const DETAIL_COVER = '.product-detail .thumbnail img';

    public const DETAIL_FIELD_ROWS = '.product-description dl > div';

    public const META_PREFIX = 'meta[name="cXenseParse:%s"]';
}
