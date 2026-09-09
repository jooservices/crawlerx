<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avfan\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Avfan\Selectors;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'Avfan';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::LISTING_LINKS)->each(function (Crawler $node) use (&$items, $request): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $title = $this->itemTitle($node);
            $externalId = $this->externalId($url);
            if ($externalId === null || $title === null || isset($items[$url])) {
                return;
            }

            $code = $this->itemCode($node, $title, $externalId);
            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->titleWithoutCode($title, $code),
                data: ['code' => $code],
            );
        });

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request->url, $request->page),
        );
    }

    private function pagination(Crawler $crawler, string $url, int $currentPage): CrawlPaginationDto
    {
        $links = [];
        $nextUrl = null;

        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $node) use (&$links, &$nextUrl, $url): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $absolute = $this->absolute($url, $href);
            $page = $this->pageFromUrl($absolute);
            if ($page !== null) {
                $links[$page] = $absolute;
            }

            $aria = strtolower(trim((string) $node->attr('aria-label')));
            $text = trim($node->text(''));
            if ($nextUrl === null && ($aria === 'next' || $text === '>' || $text === '›')) {
                $nextUrl = $absolute;
            }
        });

        $nextPage = $nextUrl !== null ? $this->pageFromUrl($nextUrl) : null;
        if ($nextPage === null) {
            $greaterPages = array_values(array_filter(array_keys($links), fn(int $page): bool => $page > $currentPage));
            sort($greaterPages);
            $nextPage = $greaterPages[0] ?? null;
            $nextUrl = $nextPage === null ? null : ($links[$nextPage] ?? null);
        }

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $links === [] ? null : max([$currentPage, ...array_keys($links)]),
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null,
        );
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/en/movies/([^/]+)#', $path, $match) === 1 ? $match[1] : null;
    }

    private function itemTitle(Crawler $node): ?string
    {
        $title = $node->attr('title');
        if (is_string($title) && trim($title) !== '') {
            return $this->normalizeText($title);
        }

        return $this->normalizeText($node->text(''));
    }

    private function itemCode(Crawler $node, ?string $title, string $externalId): string
    {
        $strong = $node->filter('strong');
        if ($strong->count() > 0) {
            $code = $this->normalizeText($strong->first()->text(''));
            if ($code !== null) {
                return $code;
            }
        }

        return CodeNormalizer::canonical($title, $externalId) ?? $externalId;
    }

    private function titleWithoutCode(?string $title, string $code): ?string
    {
        if ($title === null) {
            return null;
        }

        $trimmed = trim(preg_replace('/^\[' . preg_quote($code, '/') . '\]\s*/i', '', $title) ?? $title);

        return $trimmed !== '' ? $trimmed : $title;
    }

    private function pageFromUrl(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $page = $params['page'] ?? null;

        return is_numeric($page) && (int) $page > 0 ? (int) $page : null;
    }
}
