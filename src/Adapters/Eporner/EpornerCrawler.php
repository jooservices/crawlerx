<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Eporner;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\EpornerUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Eporner\Types\Gallery;
use JOOservices\CrawlerX\Contracts\GalleryCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * EPORNER adapter. Only photo galleries are exposed; listing and detail are
 * unsupported and satisfy the base contract so the fetch pipeline still runs.
 */
final class EpornerCrawler extends AbstractBaseCrawler implements GalleryCapable, UrlDetectCapable
{
    use DetectsUrls;
    use EpornerUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://www.eporner.com',
        'timeout' => 30,
    ];

    public function name(): string
    {
        return 'eporner';
    }

    public function gallery(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Gallery($this->client))->execute($request);
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        throw new CrawlParseException('Eporner does not support listing crawling.');
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        throw new CrawlParseException('Eporner does not support detail crawling.');
    }
}
