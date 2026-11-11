<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Venus\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Venus\Selectors;
use JOOservices\CrawlerX\Adapters\Venus\Site;
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
        foreach ($crawler->filter(Selectors::LISTING_CARDS) as $element) {
            $card = new Crawler($element);
            $link = $card->filter('a[href]')->first();
            if ($link->count() === 0) {
                continue;
            }

            $url = $this->absolute($request->url, (string) $link->attr('href'));
            $coverPath = $this->firstAttribute($card, Selectors::CARD_COVER, 'src');
            $code = Site::code($coverPath);
            if ($code === null || isset($items[$url])) {
                continue;
            }

            $performers = $card->filter(Selectors::CARD_PERFORMERS)->each(
                fn(Crawler $node): string => $this->normalizeText($node->text('')) ?? '',
            );
            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $code,
                title: $this->firstText($card, Selectors::CARD_TITLE),
                data: [
                    'code' => $code,
                    'cover_url' => $coverPath === null ? null : $this->absolute($request->url, $coverPath),
                    'date' => MovieMeta::isoDate($this->firstText($card, Selectors::CARD_DATE)),
                    'performers' => array_map(
                        static fn(string $name): array => MovieMeta::performer($name),
                        array_values(array_filter($performers, static fn(string $name): bool => $name !== '')),
                    ),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::MAKER => Site::NAME,
                        'source_fields' => MovieMeta::filter([
                            '品番' => $code,
                            '出演女優' => $performers === [] ? null : implode('、', $performers),
                            '発売日' => $this->firstText($card, Selectors::CARD_DATE),
                        ]),
                    ]),
                ],
            );
        }

        if ($items === []) {
            throw new CrawlParseException('VENUS listing did not contain expected work cards.');
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

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $currentText = $this->firstText($crawler, Selectors::PAGER_CURRENT);
        $current = $currentText !== null && ctype_digit($currentText) ? (int) $currentText : max(1, $request->page);
        $next = $this->firstAttribute($crawler, Selectors::PAGER_NEXT, 'href');
        $last = $this->firstAttribute($crawler, Selectors::PAGER_LAST, 'href');
        $lastPage = $last === null ? $current : ($this->pageNumber($last) ?? $current);
        $nextUrl = $next === null ? null : $this->absolute($request->url, $next);

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: max($current, $lastPage),
            nextPage: $nextUrl === null ? null : ($this->pageNumber($nextUrl) ?? $current + 1),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function pageNumber(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/page/(\d+)/?$#', $path, $match) === 1
            ? (int) $match[1]
            : (is_string($path) && preg_match('#/all/?$#', $path) === 1 ? 1 : null);
    }
}
