<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavLibrary;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\AliasesPerformerCapabilities;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\JavLibraryUrlDetectRules;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavLibrary\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class JavLibraryCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use AliasesPerformerCapabilities;
    use DetectsUrls;
    use JavLibraryUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://www.javlibrary.com',
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en,ja;q=0.9',
        ],
    ];

    public function name(): string
    {
        return 'javlibrary';
    }

    /**
     * @return list<array{match: string, type: class-string<TypeInterface>}>
     */
    protected function listingRoutes(): array
    {
        return [
            ['match' => 'star_list.php', 'type' => PerformerListing::class],
        ];
    }

    /**
     * @return list<array{match: string, type: class-string<TypeInterface>}>
     */
    protected function detailRoutes(): array
    {
        return [
            ['match' => 'star_info.php', 'type' => PerformerDetail::class],
            ['match' => 'vl_star.php', 'type' => PerformerDetail::class],
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
