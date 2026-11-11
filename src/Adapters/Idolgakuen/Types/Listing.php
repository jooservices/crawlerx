<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Idolgakuen\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Selectors;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Site;
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
            $href = $this->cardDetailUrl($card);
            $externalId = $href === null ? null : Site::externalId($href);
            if ($href === null || $externalId === null || isset($items[$externalId])) {
                return;
            }

            $cover = $this->firstAttribute($card, Selectors::CARD_COVER, 'src');
            $performer = $this->firstText($card, Selectors::CARD_PERFORMER);
            $items[$externalId] = MovieDto::item(
                url: $href,
                externalId: $externalId,
                title: $this->firstText($card, Selectors::CARD_TITLE),
                data: [
                    'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                    'date' => MovieMeta::isoDate($this->firstText($card, Selectors::CARD_DATE)),
                    'description' => $this->firstText($card, Selectors::CARD_DESCRIPTION),
                    'performers' => $performer === null ? [] : [MovieMeta::performer($performer)],
                    'metadata' => MovieMeta::filter([MovieMeta::MAKER => Site::NAME]),
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' listing did not contain expected work cards.');
        }

        $pagination = $this->pagination($crawler, $request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? max(1, $request->page),
            entityType: 'movie',
            items: array_values($items),
            pagination: $pagination,
        );
    }

    private function cardDetailUrl(Crawler $card): ?string
    {
        $onclick = $this->firstAttribute($card, Selectors::CARD_LINK, 'onclick');

        return $onclick !== null && preg_match("/location\\.href\\s*=\\s*'([^']+)'/", $onclick, $match) === 1
            ? $this->absolute(Site::BASE_URL, $match[1])
            : null;
    }

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $current = (int) $this->firstText($crawler, Selectors::PAGINATION_CURRENT);
        $current = $current > 0 ? $current : max(1, $request->page);
        $last = $current;
        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $link) use (&$last): void {
            $page = $this->pageNumber((string) $link->attr('href'));
            if ($page !== null) {
                $last = max($last, $page);
            }
        });
        $next = $this->firstAttribute($crawler, Selectors::PAGINATION_NEXT, 'href');
        $nextUrl = $next === null ? null : $this->absolute($request->url, $next);

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: $last,
            nextPage: $nextUrl === null ? null : ($this->pageNumber($nextUrl) ?? $current + 1),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function pageNumber(string $url): ?int
    {
        return preg_match('#/item-list/page/(\d+)/?$#', (string) parse_url($url, PHP_URL_PATH), $match) === 1
            ? (int) $match[1]
            : (preg_match('#/item-list/?$#', (string) parse_url($url, PHP_URL_PATH)) === 1 ? 1 : null);
    }
}
