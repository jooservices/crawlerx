<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Digitalark;

final class Selectors
{
    public const LISTING_CARDS = 'ul.category-item-list > li.item-list';

    public const CARD_LINK = '.item-list-name a';

    public const CARD_IMAGE = '.item-list-image img';

    public const PAGER_CURRENT = 'ul.pager span.current';

    public const PAGER_LINKS = 'ul.pager a[href]';

    public const DETAIL_TITLE = 'h1.item-name';

    public const DETAIL_COVER = '.item-image-wrap img.item-image';

    public const DETAIL_PRICE = '.item-price';

    public const DETAIL_DESCRIPTION = 'p.item-description';

    public const DETAIL_LABELLED_ROWS = 'ul.dvdlist-info li';

    public const DETAIL_PURCHASE_ROWS = '.purchase-info .price-line';

    public const DETAIL_KEYWORDS = '.keyword-links[data-keywords]';
}
