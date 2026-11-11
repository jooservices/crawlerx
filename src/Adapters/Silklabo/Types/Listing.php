<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Silklabo\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Silklabo\Selectors;
use JOOservices\CrawlerX\Adapters\Silklabo\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

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
        $crawler->filter(Selectors::CARD)->each(function ($card) use (&$items, $request): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $url = $href === null ? null : $this->absolute($request->url, $href);
            $externalId = $url === null ? null : Site::externalId($url);
            if ($url === null || $externalId === null || isset($items[$url])) {
                return;
            }

            $cover = $this->firstAttribute($card, Selectors::CARD_COVER, 'src');
            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->firstText($card, Selectors::CARD_TITLE),
                data: [
                    'code' => null,
                    'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                    'performers' => [],
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' listing did not contain expected work cards.');
        }

        $pagination = $this->pagination($crawler, $request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? 1,
            entityType: 'movie',
            items: array_values($items),
            pagination: $pagination,
        );
    }

    private function pagination(\Symfony\Component\DomCrawler\Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $currentText = $this->firstText($crawler, Selectors::PAGINATION_CURRENT);
        $currentPage = is_numeric($currentText) ? (int) $currentText : (Site::pageNumber($request->url) ?? max(1, $request->page));
        $pages = [];
        $nextUrl = null;
        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function ($link) use (&$pages, &$nextUrl, $request, $currentPage): void {
            $href = $this->firstAttribute($link, 'a', 'href');
            if ($href === null || str_starts_with($href, 'javascript:')) {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $page = Site::pageNumber($url);
            if ($page !== null) {
                $pages[] = $page;
                if ($page === $currentPage + 1) {
                    $nextUrl = $url;
                }
            }
        });

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: max([$currentPage, ...$pages]),
            nextPage: $nextUrl === null ? null : $currentPage + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }
}
