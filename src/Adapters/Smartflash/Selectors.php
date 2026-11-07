<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Smartflash;

final class Selectors
{
    public const LISTING_CARDS = '#main ul.itemList > li';

    public const CARD_LINK = 'p.itemImage a';

    public const CARD_IMAGE = 'p.itemImage img';

    public const CARD_TITLE = 'h2.itemText a';

    public const CARD_DATE = 'p.date';

    public const CARD_CATEGORY = 'p.date a';

    public const CARD_TAGS = 'ul.tagList li a';

    public const CARD_SOURCE = 'div.sourceNews a';

    public const PAGINATION_CURRENT = 'ul.pagination li.current';

    public const PAGINATION_NEXT = 'ul.pagination li.next a';

    public const PAGINATION_LAST = 'ul.pagination li.last a';

    public const ARTICLE_TITLE = '#main .inner h1';

    public const ARTICLE_CATEGORY = '#main .subHeadName';

    public const ARTICLE_BODY = '#main .newsBlock > .text';

    public const ARTICLE_IMAGES = '#main .newsBlock > .text img';

    public const ARTICLE_TAGS = '#main .inner > ul.tagList li a';

    public const ARTICLE_SOURCE = '#main .subHead.spPx10 p a';

    public const ARTICLE_AUTHOR = '#main .author__wrap a';

    public const ARTICLE_AUTHOR_IMAGE = '#main .author__wrap .image-author-small img';

    public const ARTICLE_AUTHOR_BIO = '#main .author-information';

    public const VIEWER_LINK = '#main .linkPages a.page-link[href*="/image/"]';

    public const VIEWER_ITEMS = '#main .imageSlider .item';

    public const JSON_LD = 'script[type="application/ld+json"]';
}
