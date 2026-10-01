<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavPhotos;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\JavPhotosUrlDetectRules;
use JOOservices\CrawlerX\Adapters\JavPhotos\Types\Gallery;
use JOOservices\CrawlerX\Adapters\JavPhotos\Types\Listing;
use JOOservices\CrawlerX\Contracts\GalleryCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class JavPhotosCrawler extends AbstractBaseCrawler implements GalleryCapable, ListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use JavPhotosUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://jav.photos',
    ];

    public function name(): string
    {
        return 'javphotos';
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function gallery(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Gallery($this->client))->execute($request);
    }
}
