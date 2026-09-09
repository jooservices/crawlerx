<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavLibrary\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavLibrary\Selectors;
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
        return 'JavLibrary';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::LISTING_VIDEOS)->each(function (Crawler $node) use (&$items, $request): void {
            if ($node->filter(Selectors::LISTING_LINK)->count() === 0) {
                return;
            }

            $link = $node->filter(Selectors::LISTING_LINK)->first();
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $externalId = $this->externalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $code = $this->itemCode($node, $link);
            $title = $this->itemTitle($node, $link, $code);
            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: [
                    'code' => $code,
                    'cover_url' => $this->coverUrl($node, $request->url),
                ],
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

    private function itemCode(Crawler $node, Crawler $link): string
    {
        if ($node->filter(Selectors::LISTING_CODE)->count() > 0) {
            $code = $this->normalizeText($node->filter(Selectors::LISTING_CODE)->first()->text(''));
            if ($code !== null) {
                return strtoupper($code);
            }
        }

        $title = $link->attr('title');
        $externalId = $this->externalId($this->absolute('', (string) $link->attr('href')));

        return CodeNormalizer::canonical(is_string($title) ? $title : null, $externalId) ?? strtoupper((string) $externalId);
    }

    private function itemTitle(Crawler $node, Crawler $link, string $code): ?string
    {
        $fromDiv = $node->filter(Selectors::LISTING_TITLE)->count() > 0
            ? $this->normalizeText($node->filter(Selectors::LISTING_TITLE)->first()->text(''))
            : null;

        $fromAttr = $link->attr('title');
        $title = $fromDiv ?? (is_string($fromAttr) && trim($fromAttr) !== '' ? $this->normalizeText($fromAttr) : null);
        if ($title === null) {
            return null;
        }

        $trimmed = trim(preg_replace('/^' . preg_quote($code, '/') . '\s+/i', '', $title) ?? $title);

        return $trimmed !== '' ? $trimmed : $title;
    }

    private function coverUrl(Crawler $node, string $baseUrl): ?string
    {
        $image = $node->filter('img[src]');
        if ($image->count() === 0) {
            return null;
        }

        $src = $image->first()->attr('src');

        return is_string($src) && trim($src) !== '' ? $this->absolute($baseUrl, $src) : null;
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

            $class = (string) $node->attr('class');
            $text = trim($node->text(''));
            if ($nextUrl === null && (str_contains($class, 'next') || $text === '>' || $text === '›')) {
                $nextUrl = $absolute;
            }
        });

        $lastPage = $this->lastPageFromSelector($crawler, $url);
        $nextPage = $nextUrl !== null ? $this->pageFromUrl($nextUrl) : null;
        if ($nextPage === null) {
            $greaterPages = array_values(array_filter(array_keys($links), fn(int $page): bool => $page > $currentPage));
            sort($greaterPages);
            $nextPage = $greaterPages[0] ?? null;
            $nextUrl = $nextPage === null ? null : ($links[$nextPage] ?? null);
        }

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $lastPage ?? ($links === [] ? null : max([$currentPage, ...array_keys($links)])),
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null,
        );
    }

    private function lastPageFromSelector(Crawler $crawler, string $url): ?int
    {
        $node = $crawler->filter(Selectors::PAGINATION_LAST);
        if ($node->count() === 0) {
            return null;
        }

        $href = $node->first()->attr('href');
        if (! is_string($href) || trim($href) === '') {
            return null;
        }

        return $this->pageFromUrl($this->absolute($url, $href));
    }

    private function externalId(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            $id = $params['v'] ?? null;
            if (is_string($id) && trim($id) !== '') {
                return trim($id);
            }
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match('#/([a-z0-9]+)\.html$#i', $path, $match) !== 1) {
            return null;
        }

        return $match[1];
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
