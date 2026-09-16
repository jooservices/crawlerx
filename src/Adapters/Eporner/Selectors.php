<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Eporner;

final class Selectors
{
    public const GALLERY_TITLE = '.gallery-heading h1';

    public const GALLERY_META_STRONGS = '.gallery-meta strong';

    public const GALLERY_RATING = '.gallery-rating strong';

    public const GALLERY_VOTES = '.gallery-votes';

    public const GALLERY_UPLOADER = '.gallery-uploader';

    public const GALLERY_DATE = '.gallery-meta time';

    public const GALLERY_PORNSTARS = '.vit-pornstar a';

    public const GALLERY_CATEGORIES = '.vit-category a';

    public const GALLERY_TAGS = '.vit-tag a';

    public const GALLERY_PHOTOS = '.photosgrid.gallerygrid .mbphoto2[data-gallery-photo]';

    public const PHOTO_LINK = 'a.gallery-photo-image';

    public const PHOTO_IMAGE = 'a.gallery-photo-image img';

    public const PHOTO_NUMBER = '.gallery-photo-number';

    public const PHOTO_VIEWS = '.gallery-photo-full-views';

    public const PHOTO_RATING = '.gallery-photo-rating';
}
