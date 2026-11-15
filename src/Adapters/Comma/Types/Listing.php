<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Comma\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Comma\Selectors;
use JOOservices\CrawlerX\Adapters\Comma\Site;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The yearly release archive (/ym/<year>/) has no pagination in its markup: the page lists every release of the year
 * and carries no next or page links, so the listing is always a single page.
 */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

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
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request): void {
            $url = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $code = $this->firstText($card, Selectors::CARD_CODE);
            if ($url === null || $code === null || isset($items[$code])) {
                return;
            }

            $url = $this->absolute($request->url, $url);
            $cover = $this->firstAttribute($card, Selectors::CARD_IMAGE, 'src');

            $items[$code] = MovieDto::item($url, $code, $this->firstText($card, Selectors::CARD_TITLE), [
                'code' => $code,
                'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                'metadata' => MovieMeta::filter([MovieMeta::MAKER => Site::NAME]),
            ]);
        });

        if ($items === []) {
            throw new CrawlParseException('COMMA VISION listing page did not contain release cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: 1,
            entityType: 'movie',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: 1,
                lastPage: 1,
                nextPage: null,
                nextUrl: null,
                hasNextPage: false,
            ),
        );
    }
}
