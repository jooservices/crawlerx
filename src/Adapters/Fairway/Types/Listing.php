<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fairway\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Fairway\Site;
use JOOservices\CrawlerX\Adapters\Fairway\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

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

        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$items, $request): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $url = $href === null ? null : $this->absolute($request->url, $href);
            $externalId = $url === null ? null : Site::externalId($url);
            if ($url === null || $externalId === null || isset($items[$url])) {
                return;
            }

            $image = $this->firstAttribute($card, Selectors::CARD_COVER, 'data-src')
                ?? $this->firstAttribute($card, Selectors::CARD_COVER, 'src');
            $group = $card->ancestors()->filter(Selectors::CARD_GROUP)->first();
            $heading = $group->count() > 0 ? $group->previousAll()->filter('.p-search__key')->first() : new Crawler();
            $date = MovieMeta::isoDate($this->firstText($heading, 'p'));
            $performers = $this->texts($card, Selectors::CARD_PERFORMERS);

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->firstText($card, Selectors::CARD_TITLE),
                data: [
                    'code' => $externalId,
                    'cover_url' => $image === null ? null : $this->absolute($request->url, $image),
                    'date' => $date,
                    'performers' => array_map(static fn(string $name): array => MovieMeta::performer($name), $performers),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::MAKER => Site::NAME,
                        'release_group' => $this->firstText($heading, 'p'),
                    ]),
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' listing did not contain expected work cards.');
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
