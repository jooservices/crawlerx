<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom;

final class CaribbeanComCrawler extends AbstractCaribbeanCrawler
{
    protected array $options = ['base_uri' => 'https://en.caribbeancom.com'];

    protected function profile(): Profile
    {
        return Profile::caribbeancom();
    }
}
