<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Javbtc;

final class Selectors
{
    public const LISTING_CARDS = 'div.a4 > a[href^="/r18/"]';

    public const PAGINATION_LINKS = 'div.a3a a[href], div.a9 a[href]';

    public const PAGINATION_CURRENT = 'div.a3a b, div.a9 b';

    public const DETAIL_BREADCRUMB = 'div.a9';

    public const DETAIL_VIDEO = 'div.a8 video';

    public const DETAIL_DESCRIPTION = 'div.a8 + div.a6 a';

    public const DETAIL_TAG_BLOCK = 'div.a6';

    public const DETAIL_META_DESCRIPTION = 'meta[name="description"]';

    public const ITEM_URL_PATTERN = '#^https://javbtc\.com/r18/[^/]+$#i';

    public const NUMERIC_SLUG_PATTERN = '#^/r18/\d+/?$#';
}
