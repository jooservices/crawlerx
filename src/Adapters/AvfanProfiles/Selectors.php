<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvfanProfiles;

final class Selectors
{
    public const PERFORMER_CARDS = 'li a.actress-list-title[href*="/actress/"]';

    public const PERFORMER_NAME = '.a-name';

    public const PERFORMER_NAME_KANA = '.a-hurigana';

    public const PROFILE_IMAGE = '.actress-img img[src]';

    public const BIO_ROWS = 'div.sen';

    public const BIO_LABEL = '.koumoku';

    public const BIO_VALUE = '.atai';

    public const SNS_LINKS = 'a[href*="twitter.com/"], a[href*="x.com/"], a[href*="instagram.com/"], a[href*="youtube.com/"], a[href*="tiktok.com/"]';
}
