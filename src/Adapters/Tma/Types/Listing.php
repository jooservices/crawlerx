<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Tma\ListingCards;
use JOOservices\CrawlerX\Adapters\Tma\ListingPagination;
use JOOservices\CrawlerX\Adapters\Tma\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $dom = $this->fetchCrawler($request);
        $items = (new ListingCards())->items($dom);
        if ($items === []) {
            throw new CrawlParseException('TMA listing did not contain expected product cards.');
        }

        $pagination = (new ListingPagination())->read($dom, $request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? $request->page,
            entityType: 'movie',
            items: $items,
            pagination: $pagination,
        );
    }
}
