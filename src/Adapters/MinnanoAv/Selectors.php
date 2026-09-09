<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv;

final class Selectors
{
    public const PERFORMER_LINKS = 'table.tbllist a[href*="actress"][href*=".html"], a.actress-link[href*="actress"]';

    public const PAGINATION_NEXT = 'link[rel="next"], .pagination a.next, .pagination a[href*="page="]:last-of-type';

    public const PROFILE_TABLE = '.act-profile table';

    public const PROFILE_ROW = '.act-profile table tr';

    public const PROFILE_LABEL = 'span';

    public const PROFILE_TAGS = '.act-profile .tagarea a';

    public const PERFORMER_HEADING = 'h1';

    public const PERFORMER_IMAGE = '.act-area .thumb img, img.actressThumb';

    public const RATING_TABLE = '.rate-table';

    public const FILMOGRAPHY_MOVIE_LINKS = 'a[href*="av"][href$=".html"]';

    public const AV_DETAIL_TITLE = 'h1, .av-title, title';
}
