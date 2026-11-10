<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ReadsPathNumbers;
use JOOservices\CrawlerX\Adapters\Dahlia\Fields;
use JOOservices\CrawlerX\Adapters\Dahlia\Selectors;
use JOOservices\CrawlerX\Adapters\Dahlia\Site;
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
    use ReadsPathNumbers;

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
        $items = [];

        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$items, $request, $fields): void {
            $url = $fields->link($card, Selectors::CARD_LINK, $request->url);
            $externalId = $url === null ? null : Site::workId($url);
            if ($url === null || $externalId === null || isset($items[$url])) {
                return;
            }

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->firstText($card, Selectors::CARD_TITLE),
                data: [
                    'code' => Site::code($externalId),
                    'cover_url' => $fields->image($card, Selectors::CARD_COVER, $request->url),
                    'performers' => $fields->cast($this->firstText($card, Selectors::CARD_CAST)),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::MAKER => Site::NAME,
                        MovieMeta::SAMPLE_VIDEO_URL => $fields->link($card, Selectors::SAMPLE_VIDEO, $request->url),
                        'release_notes' => $fields->releaseNotes($card, Selectors::CARD_RELEASE_NOTES),
                        'purchase_links' => $fields->purchaseLinks($card, Selectors::CARD_PURCHASE_LINKS),
                    ]),
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

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $current = (int) $this->firstText($crawler, Selectors::PAGINATION_CURRENT);
        $current = $current > 0 ? $current : max(1, $request->page);
        $next = $this->firstAttribute($crawler, Selectors::PAGINATION_NEXT, 'href');
        $nextUrl = $next === null ? null : $this->absolute($request->url, $next);
        $hrefs = $crawler->filter(Selectors::PAGINATION_LAST . ', ' . Selectors::PAGINATION_PAGES)
            ->each(static fn(Crawler $node): string => (string) $node->attr('href'));
        $pages = array_map(fn(string $href): int => $this->pathNumber($href, '#/page/(\d+)/?#') ?? 0, $hrefs);

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: max([$current, ...$pages]),
            nextPage: $nextUrl === null ? null : ($this->pathNumber($nextUrl, '#/page/(\d+)/?#') ?? $current + 1),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }
}
