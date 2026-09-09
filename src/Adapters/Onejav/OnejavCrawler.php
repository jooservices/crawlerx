<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Onejav;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\TorrentThemeUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Onejav\Types\Detail;
use JOOservices\CrawlerX\Adapters\Onejav\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\TagActressListing;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class OnejavCrawler extends AbstractBaseCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use TorrentThemeUrlDetectRules;

    public function name(): string
    {
        return 'onejav';
    }

    /**
     * @return list<array{match: callable(CrawlRequestDto): bool, type: class-string<TypeInterface>}>
     */
    protected function listingRoutes(): array
    {
        return [
            [
                'match' => fn(CrawlRequestDto $request): bool => str_contains($request->url, '/tag/')
                    || str_contains($request->url, '/actress/'),
                'type' => TagActressListing::class,
            ],
        ];
    }

    protected function defaultListingType(): string
    {
        return Listing::class;
    }

    protected function defaultDetailType(): string
    {
        return Detail::class;
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        /** @var CrawlListResultDto $result */
        $result = $this->makeType($this->resolveListingType($request))->execute($request);

        return $result;
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        /** @var CrawlItemResultDto $result */
        $result = $this->makeType($this->resolveDetailType($request))->execute($request);

        return $result;
    }
}
