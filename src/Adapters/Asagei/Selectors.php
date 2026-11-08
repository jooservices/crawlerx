<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Asagei;

final class Selectors
{
    public const LISTING_CARDS = 'main.main-area .content-area > .content-list';

    public const LISTING_TITLE = 'h1.content-list-page-title';

    public const CARD_TITLE = 'h2.content-title a';

    public const CARD_DATE = '.cat-head-box .posted-at';

    public const CARD_CATEGORY_HEAD = '.cat-head-box .cat-head';

    public const CARD_THUMB = '.content-thumb-pc img, .content-thumb-sp img';

    public const CARD_EXCERPT = '.content-body > p';

    public const PAGINATION_CURRENT = '.pagination .page-numbers.current';

    public const PAGINATION_NEXT = '.pagination a.next.page-numbers';

    public const PAGINATION_LAST = '.pagination a.last.page-numbers';

    public const ARTICLE_TITLE = 'h1.entry-title';

    public const ARTICLE_DATE = '.cat-head-box .posted-at, .sp-posted-at';

    public const ARTICLE_DATETIME = '.sp-posted-at';

    public const ARTICLE_CATEGORY_HEAD = '.content-area > .cat-head-box .cat-head';

    public const ARTICLE_BODY = '#entry-content';

    public const ARTICLE_IMAGES = '#entry-content img';

    public const ARTICLE_PREV = '.entry-utility-article-navigation .nav-previous a';

    public const ARTICLE_NEXT = '.entry-utility-article-navigation .nav-next a';

    public const OG_IMAGE = 'meta[property="og:image"]';

    public const OG_DESCRIPTION = 'meta[property="og:description"]';
}
