<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fairway;

final class Selectors
{
    public const CARD = '.p-search .c-card';

    public const CARD_LINK = 'a.img[href*="/works/detail/"]';

    public const CARD_COVER = 'a.img img';

    public const CARD_TITLE = '.hover__child .text';

    public const CARD_PERFORMERS = '.name';

    public const CARD_GROUP = '.l-wrap';

    public const DETAIL_BODY = '.p-workPage';

    public const DETAIL_TITLE = '.p-workPage__title';

    public const DETAIL_DESCRIPTION = '.p-workPage__text';

    public const DETAIL_ROWS = '.p-workPage__table > .item';

    public const DETAIL_GALLERY = '.p-slider.-work .swiper-slide';
}
