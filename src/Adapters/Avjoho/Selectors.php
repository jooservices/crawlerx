<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avjoho;

final class Selectors
{
    public const PERFORMER_LINKS = '.post .entry-title a[href*="db.avjoho.com/"], .post .entry-title a[href^="/"]';

    public const PAGE_LINKS = '#list .pagination a[href*="/page/"], .pagination a[href*="/page/"]';

    public const PAGE_CURRENT = '.pagination .current, .pagination span[aria-current="page"]';

    public const PERFORMER_NAME = 'h1.entry-title';

    public const PROFILE_TABLE = '.database .profile table';

    public const BIRTHPLACE_TABLE = '.database .birthplace table';

    public const BLOOD_TYPE_TABLE = '.database .blood-type table';

    public const HOBBY_TABLE = '.database .shumi-tokugi table';

    public const ALIAS_TABLE = '.database .name table';

    public const MAKER_TABLE = '.database .maker table';

    public const SNS_TABLE = '.database .sns table';

    public const BIO_TEXT = '.database .profile2';

    public const COVER_IMAGE = '.database .gazou img[src]';
}
