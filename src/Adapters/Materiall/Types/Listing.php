<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Materiall\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Falenogroup\Fields as StoreFields;
use JOOservices\CrawlerX\Adapters\Materiall\Fields;
use JOOservices\CrawlerX\Adapters\Materiall\Selectors;
use JOOservices\CrawlerX\Adapters\Materiall\Site;
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
        $fields = new Fields();
        $stores = new StoreFields();
        $items = [];

        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$items, $request, $fields, $stores): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $url = $href === null ? null : $this->absolute($request->url, $href);
            $externalId = $url === null ? null : Site::externalId($url);
            if ($url === null || $externalId === null || isset($items[$url])) {
                return;
            }

            $cover = $fields->lazyImage($card, Selectors::CARD_COVER);
            $reservation = $fields->timerDate($card, Selectors::CARD_RESERVATION);
            $distribution = $fields->timerDate($card, Selectors::CARD_DISTRIBUTION);

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->firstText($card, Selectors::CARD_TITLE),
                data: [
                    'code' => Site::code($externalId),
                    'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                    'date' => $distribution ?? $reservation,
                    'performers' => array_map(
                        static fn(string $name): array => MovieMeta::performer($name),
                        Site::castNames($this->firstText($card, Selectors::CARD_CAST)),
                    ),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::MAKER => Site::NAME,
                        'reservation_start_date' => $reservation,
                        'distribution_date' => $distribution,
                        'purchase_links' => $stores->purchaseLinks($card, Selectors::CARD_PURCHASE_LINKS),
                    ]),
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' listing did not contain expected work cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request),
        );
    }

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $current = (int) $this->firstText($crawler, Selectors::PAGINATION_CURRENT);
        $current = $current > 0 ? $current : max(1, $request->page);
        $next = $this->firstAttribute($crawler, Selectors::PAGINATION_NEXT, 'href');
        $nextUrl = $next === null ? null : $this->absolute($request->url, $next);
        $pages = $crawler->filter(Selectors::PAGINATION_LAST . ', ' . Selectors::PAGINATION_PAGES)
            ->each(fn(Crawler $node): int => $this->pageNumber((string) $node->attr('href')) ?? 0);

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: max([$current, ...$pages]),
            nextPage: $nextUrl === null ? null : ($this->pageNumber($nextUrl) ?? $current + 1),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function pageNumber(string $href): ?int
    {
        return preg_match('#/page/(\d+)/?#', $href, $match) === 1 ? (int) $match[1] : null;
    }
}
