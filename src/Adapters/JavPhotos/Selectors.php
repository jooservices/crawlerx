<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavPhotos;

final class Selectors
{
    public const LISTING_GALLERIES = '.pinbox a[href*="/free/"]';

    public const PAGINATION_NEXT = '.header h2 a[target="_top"], .footer h2 a[target="_top"]';

    public const GALLERY_PHOTOS = '.pinbox.pinimg a[href*="/pictures/"]';

    public const GALLERY_FOOTER_LINKS = '.footer h2 a[href*="/free/"]';
}
