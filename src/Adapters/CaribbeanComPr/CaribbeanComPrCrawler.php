<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanComPr;

use JOOservices\CrawlerX\Adapters\CaribbeanCom\AbstractCaribbeanCrawler;
use JOOservices\CrawlerX\Adapters\CaribbeanCom\Profile;

final class CaribbeanComPrCrawler extends AbstractCaribbeanCrawler
{
    protected array $options = ['base_uri' => 'https://en.caribbeancompr.com'];

    protected function profile(): Profile
    {
        return Profile::caribbeancompr();
    }
}
