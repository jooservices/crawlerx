<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavBus;

final class Selectors
{
    public const MOVIE_BOXES = '#waterfall .movie-box';

    public const MOVIE_LINKS = '#waterfall .movie-box a[href]';

    public const PAGINATION_LINKS = 'ul.pagination a';

    public const DETAIL_TITLE = '#video_title h3, h3#video_title';

    public const DETAIL_COVER = 'a.bigImage img, .bigImage img';

    public const DETAIL_INFO = '.info p';

    public const DETAIL_INFO_HEADER = 'span.header';

    public const DETAIL_PERFORMER_LINKS = '#star-name a[href*="/star/"], .star-name a[href*="/star/"]';

    public const DETAIL_SAMPLE_LINKS = '#sample-waterfall a.sample-box';

    public const PERFORMER_LINKS = '#waterfall a[href*="/star/"]';

    public const PERFORMER_NAME = 'h3';

    public const PERFORMER_ALIAS = 'span.alias';

    public const PERFORMER_IMAGE = '.photo-frame img, .avatar img';

    public const PERFORMER_INFO = '.photo-info span';

    public const PERFORMER_TAG_LINKS = 'a[href*="/genre/"]';
}
