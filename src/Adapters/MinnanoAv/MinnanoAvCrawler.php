<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\MinnanoAvUrlDetectRules;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Detail;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Listing;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class MinnanoAvCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use MinnanoAvUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://www.minnano-av.com',
    ];

    public function name(): string
    {
        return 'minnanoav';
    }

    /**
     * @return list<array{match: string, type: class-string<TypeInterface>}>
     */
    protected function listingRoutes(): array
    {
        return [
            ['match' => 'actress.php', 'type' => Listing::class],
        ];
    }

    /**
     * @return list<array{match: string, type: class-string<TypeInterface>}>
     */
    protected function detailRoutes(): array
    {
        return [
            ['match' => 'actress', 'type' => PerformerDetail::class],
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

    public function performerListing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new PerformerListing($this->client))->execute($request);
    }

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client))->execute($request);
    }
}
