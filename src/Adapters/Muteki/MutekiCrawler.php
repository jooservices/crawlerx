<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muteki;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Muteki\Types\Detail;
use JOOservices\CrawlerX\Adapters\Muteki\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class MutekiCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    protected function makeType(string $typeClass): TypeInterface
    {
        return new $typeClass($this->client);
    }

    public function name(): string
    {
        return 'muteki';
    }
}
