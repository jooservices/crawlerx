<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Plum\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Plum\Cast;
use JOOservices\CrawlerX\Adapters\Plum\Selectors;
use JOOservices\CrawlerX\Adapters\Plum\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Plum', 'listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request): void {
            $item = $this->card($card, $request->url);
            if ($item !== null) {
                $items[$item->url] = $item;
            }
        });

        if ($items === []) {
            throw new CrawlParseException('Plum listing page did not contain expected movie fields.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request),
        );
    }

    private function card(Crawler $card, string $pageUrl): ?CrawlItemResultDto
    {
        $href = $this->firstAttribute($card, Selectors::LISTING_LINK, 'href');
        if ($href === null) {
            return null;
        }

        $url = $this->normalizer->absolute($pageUrl, $href);
        $slug = basename(rtrim((string) parse_url($url, PHP_URL_PATH), '/'));
        $cover = $this->firstAttribute($card, Selectors::LISTING_IMAGE, 'src');
        $coverUrl = $cover === null ? null : $this->normalizer->absolute($pageUrl, $cover);
        $badge = $this->firstText($card, Selectors::LISTING_BADGE);
        $date = MovieMeta::isoDate($badge);
        $cast = $this->firstText($card, Selectors::LISTING_CAST);

        return MovieDto::item($url, $slug, $this->firstText($card, Selectors::LISTING_TITLE), MovieMeta::filter([
            'code' => $date === null ? $badge : $this->codeFromCover($coverUrl),
            'date' => $date,
            'cover_url' => $coverUrl,
            'performers' => Cast::performers($cast),
        ]));
    }

    private function codeFromCover(?string $coverUrl): ?string
    {
        return $coverUrl !== null && preg_match(Selectors::CODE_IN_COVER_PATH, $coverUrl, $match) === 1 ? $match[1] : null;
    }

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $current = (int) ($this->firstText($crawler, Selectors::PAGE_CURRENT) ?? $request->page);
        $pages = [$current];
        $crawler->filter(Selectors::PAGE_LINKS)->each(function (Crawler $link) use (&$pages): void {
            $page = $this->pageNumber($link->attr('href') ?? '');
            if ($page !== null) {
                $pages[] = $page;
            }
        });

        $next = $this->firstAttribute($crawler, Selectors::LINK_NEXT, 'href');
        $nextUrl = $next === null ? null : $this->normalizer->absolute($request->url, $next);

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: max($pages),
            nextPage: $nextUrl === null ? null : $this->pageNumber($nextUrl),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function pageNumber(string $url): ?int
    {
        return preg_match('#/page/(\d+)/?#', $url, $match) === 1 ? (int) $match[1] : null;
    }
}
