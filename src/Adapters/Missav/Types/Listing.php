<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Missav\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Missav\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use InteractsWithMissavHtml;

    /** @var list<string> */
    private const LOCALES = ['cn', 'de', 'en', 'fil', 'fr', 'id', 'ja', 'ko', 'ms', 'pt', 'th', 'vi'];

    /** @var list<string> */
    private const LISTING_PATHS = [
        'latest-updates',
        'new',
        'release',
        'uncensored-leak',
        'english-subtitle',
    ];

    protected function siteLabel(): string
    {
        return 'MissAV';
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

            $absolute = $this->absolute($request->url, $href);
            if (! $this->isItemUrl($absolute)) {
                return;
            }

            $externalId = $this->externalId($absolute);
            if ($externalId === null) {
                return;
            }

            if (isset($items[$absolute])) {
                return;
            }

            $title = $this->itemTitle($node);
            $items[$absolute] = MovieDto::item(
                url: $absolute,
                externalId: $externalId,
                title: $title,
                data: ['code' => CodeNormalizer::canonical($title, $externalId)],
            );
        });

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->parsePagination($crawler, $request->url, $request->page),
        );
    }

    private function parsePagination(Crawler $crawler, string $url, int $currentPage): CrawlPaginationDto
    {
        $pageLinks = [];
        $explicitNextUrl = null;

        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $node) use (&$pageLinks, &$explicitNextUrl, $url): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $absolute = $this->absolute($url, $href);
            $label = strtolower(trim($node->text('')));
            $rel = strtolower((string) $node->attr('rel'));
            $page = $this->pageFromUrl($absolute);

            if (($rel === 'next' || $label === 'next' || $label === '›' || $label === '>') && $this->isListingUrl($absolute)) {
                $explicitNextUrl = $absolute;
            }

            if ($page !== null && $this->isListingUrl($absolute)) {
                $pageLinks[$page] = $absolute;
            }
        });

        $visiblePages = array_keys($pageLinks);
        $nextPage = null;
        $nextUrl = null;

        if ($explicitNextUrl !== null) {
            $nextPage = $this->pageFromUrl($explicitNextUrl) ?? $currentPage + 1;
            $nextUrl = $explicitNextUrl;
        } else {
            $greaterPages = array_values(array_filter($visiblePages, fn(int $page): bool => $page > $currentPage));
            sort($greaterPages);
            $nextPage = $greaterPages[0] ?? null;
            $nextUrl = $nextPage === null ? null : ($pageLinks[$nextPage] ?? null);
        }

        $lastPage = $visiblePages === [] ? null : max([$currentPage, ...$visiblePages]);

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $lastPage,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null,
        );
    }

    private function isItemUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return false;
        }

        $segments = $this->contentPathSegments($path);
        if (count($segments) !== 1) {
            return false;
        }

        $slug = $segments[0];
        if (in_array($slug, [...self::LISTING_PATHS, 'genres', 'tags', 'actresses', 'makers', 'search'], true)) {
            return false;
        }

        return preg_match('/^(?=.*\d)[a-z0-9]+(?:-[a-z0-9]+)+$/i', $slug) === 1;
    }

    private function isListingUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return false;
        }

        $segments = $this->contentPathSegments($path);

        return count($segments) === 1 && in_array($segments[0], self::LISTING_PATHS, true);
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim(basename($path), '/') : null;
    }

    private function itemTitle(Crawler $node): ?string
    {
        $title = $node->attr('title');
        if (is_string($title) && trim($title) !== '') {
            return $this->normalizeText($title);
        }

        if ($node->filter('img[alt]')->count() > 0) {
            $alt = $node->filter('img[alt]')->first()->attr('alt');
            if (is_string($alt) && trim($alt) !== '') {
                return $this->normalizeText($alt);
            }
        }

        return $this->normalizeText($node->text(''));
    }

    private function pageFromUrl(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        $params = [];
        parse_str($query, $params);
        $page = $params['page'] ?? null;

        return is_numeric($page) && (int) $page > 0 ? (int) $page : null;
    }

    /** @return list<string> */
    private function contentPathSegments(string $path): array
    {
        $segments = array_values(array_filter(explode('/', trim(strtolower($path), '/')), static fn(string $segment): bool => $segment !== ''));
        if (isset($segments[0]) && in_array($segments[0], self::LOCALES, true)) {
            array_shift($segments);
        }

        return $segments;
    }
}
