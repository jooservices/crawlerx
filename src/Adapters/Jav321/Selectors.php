<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jav321;

final class Selectors
{
    public const DETAIL_PANEL = '.panel.panel-info';

    public const DETAIL_TITLE = '.panel-heading h3';

    public const DETAIL_CODE = '.panel-heading h3 small';

    public const DETAIL_COVER = '.panel-body .col-md-3 img';

    public const DETAIL_INFO = '.panel-body .col-md-9';

    public const DETAIL_VIDEO = 'video#vjs_sample_player';

    public const DETAIL_SOURCE = 'video#vjs_sample_player source';

    public const DETAIL_DESCRIPTION = '.panel-body .col-md-12';

    public const DETAIL_SNAPSHOT_IMAGES = 'a[href*="/snapshot/"] img';

    public const SERIES_PANEL = '.panel-heading a[href*="/series/"]';

    public const RELATED_CARDS = '.thumbnail a[href^="/video/"]';

    public const MOVIE_CARDS = 'a[href^="/video/"]';

    public const MOVIE_IMAGE = 'img';

    public const DOWNLOAD_BADGE = '.glyphicon.glyphicon-download';

    public const PERFORMER_PHOTO_CARDS = '.thumbnail a[href^="/star/"]';

    public const PERFORMER_LINKS = 'a[href^="/star/"]';

    public const PERFORMER_NAME = 'h4';

    public const PERFORMER_IMAGE = 'img';

    public const FILMOGRAPHY_CARDS = 'a[href^="/video/"]';

    public const NEXT_LINK = 'ul.pager li.next a[href]';
}
