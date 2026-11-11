<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gentosha;

final class Selectors
{
    public const LISTING_CARDS = '.m-cardHighlight__item';

    public const LISTING_LINK = 'a.m-cardHighlight__book[href]';

    public const LISTING_TITLE = '.m-cardHighlight__bookHeading';

    public const LISTING_AUTHOR = '.m-cardHighlight__bookAuthor';

    public const LISTING_TYPES = '.m-cardHighlight__bookType > div';

    public const LISTING_PRICE = '.m-cardHighlight__bookPrice';

    public const LISTING_DATE = '.m-cardHighlight__bookPublished time[datetime]';

    public const LISTING_DATE_TEXT = '.m-cardHighlight__bookPublished time';

    public const LISTING_COVER = '.m-cardHighlight__bookImg img';

    public const PAGER_CURRENT = '.m-pager__page.-active';

    public const PAGER_LINKS = '.m-pager__page[href]';

    public const PAGER_NEXT = '.m-pager__next[href]';

    public const PAGER_LAST = '.m-pager__end[href]';

    public const DETAIL_TITLE = '.p-book-title';

    public const DETAIL_AUTHORS = '.p-book-author__item';

    public const DETAIL_COVER = '.p-book-img__sliderMain-item img';

    public const DETAIL_COVER_THUMBNAIL = '.p-book-img__sliderThum-item img';

    public const DETAIL_COVER_FULL = '.p-book-img__modalSlider-itemBack img';

    public const DETAIL_RELEASE_DATE = '.p-book-published time[datetime]';

    public const DETAIL_RELEASE_TEXT = '.p-book-published';

    public const DETAIL_PRICE = '.p-book-price';

    public const DETAIL_PRICE_NOTE = '.p-book-note';

    public const DETAIL_INFO_ROWS = '.p-book-infoDataItem';

    public const DETAIL_PURCHASE_LINKS = '.p-book-storeList a[data-store][href]';

    public const DETAIL_CATEGORY = '.p-book-genre__item';

    public const DETAIL_DESCRIPTION = '.p-book-introductionBody .m-article';

    public const DETAIL_WORK_IMAGES = '.p-book-introductionBody .m-article img';

    public const JSON_LD = 'script[type="application/ld+json"]';
}
