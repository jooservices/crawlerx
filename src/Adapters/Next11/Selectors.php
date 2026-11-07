<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Next11;

final class Selectors
{
    public const LISTING_CARDS = 'ul.listarea > li';

    public const LISTING_LINK = '.listphoto a[href*="product_id="]';

    public const LISTING_TITLE = 'h2.namecenter';

    public const LISTING_IMAGE = '.listphoto img';

    public const LISTING_ROWS = 'dl.listproducts';

    public const LISTING_NOTE = 'p.before_sale';

    public const PAGER_LINKS = 'nav.pager a[href*="pageno="]';

    public const PAGER_CURRENT = 'nav.pager strong[aria-current="page"]';

    public const PAGER_NEXT = 'nav.pager li.pager_next a[href]';

    public const DETAIL_ARTICLE = 'article.product';

    public const DETAIL_TITLE = 'article.product h1';

    public const DETAIL_ROWS = 'dl.detail2';

    public const DETAIL_COVER_LINK = '.detail1 a[href]';

    public const DETAIL_COVER_IMAGE = '.detail1 img';

    public const DETAIL_SAMPLE_LINKS = '.detail_thumbs a[href]';

    public const DETAIL_BADGES = '.detail3 img';

    public const DETAIL_NOTE = '.detail3 p';

    public const DETAIL_REVIEW = 'p.review';

    public const PURCHASE_CARDS = '.fanza_purchase a.fanza_purchase_card';

    public const SAMPLE_VIDEO = '.fanza_sample_movie iframe';

    public const OG_IMAGE = 'meta[property="og:image"]';

    public const JSON_LD = 'script[type="application/ld+json"]';
}
