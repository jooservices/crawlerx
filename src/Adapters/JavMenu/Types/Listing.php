<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\JavMenu\Cards;
use JOOservices\CrawlerX\Adapters\JavMenu\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * Any paged work grid: a section (`/censored`), a code prefix (`/code/DSOD`) or a maker, series, genre or director.
 */
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
        $crawler = $this->fetchCrawler($request);
        $cards = new Cards();
        $items = [];
        foreach ($cards->works($crawler, $request->url) as $work) {
            $items[] = MovieDto::item(
                url: $work['url'],
                externalId: $work['external_id'],
                title: $work['title'],
                data: [
                    'code' => $work['code'],
                    'date' => $work['date'],
                    'cover_url' => $work['cover_url'],
                    'metadata' => [
                        'online_available' => $work['online_available'],
                        'downloadable' => $work['downloadable'],
                        'is_new' => $work['is_new'],
                    ],
                ],
            );
        }

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' listing did not contain expected work cards.');
        }

        $pagination = $cards->pagination($crawler, $request->url, $request->page);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? 1,
            entityType: 'movie',
            items: $items,
            pagination: $pagination,
        );
    }
}
