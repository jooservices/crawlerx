<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Javbtc\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Javbtc\Selectors;
use JOOservices\CrawlerX\Adapters\Javbtc\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
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
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Javbtc', 'listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $node) use (&$items, $request): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            if (preg_match(Selectors::NUMERIC_SLUG_PATTERN, $href) === 1) {
                return;
            }

            $absolute = $this->normalizer->absolute($request->url, $href);
            if (preg_match(Selectors::ITEM_URL_PATTERN, $absolute) !== 1) {
                return;
            }

            $path = parse_url($absolute, PHP_URL_PATH);
            $externalId = trim(basename(is_string($path) ? $path : ''), '/');
            if ($externalId === '') {
                return;
            }

            $cover = $node->filter('img')->count() > 0 ? $node->filter('img')->first()->attr('src') : null;
            $title = $node->filter('img')->count() > 0
                ? $this->normalizeText($node->filter('img')->first()->attr('alt') ?? '')
                : $this->normalizeText($node->text(''));

            $items[$absolute] = MovieDto::item(
                url: $absolute,
                externalId: $externalId,
                title: $title,
                data: array_filter([
                    'cover_url' => is_string($cover) && $cover !== ''
                        ? $this->normalizer->absolute($request->url, $cover)
                        : null,
                    'metadata' => array_filter([
                        'clip_duration_label' => $this->cardDurationLabel($node),
                    ], static fn(mixed $value): bool => is_string($value) && $value !== ''),
                ], static fn(mixed $value): bool => $value !== null && $value !== []),
            );
        });

        if ($items === []) {
            throw new \RuntimeException('Javbtc listing page did not contain expected movie fields.');
        }

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
        if ($currentPage < 1) {
            $currentPage = 1;
        }

        /** @var array<int, string|null> $pageLinks */
        $pageLinks = [];
        $explicitNextUrl = null;
        $lastPageUrl = null;

        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $node) use (
            &$pageLinks,
            &$explicitNextUrl,
            &$lastPageUrl,
            $url,
        ): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $absolute = $this->normalizer->absolute($url, $href);
            $label = trim($node->text(''));
            $page = $this->pageFromUrl($absolute, $url);

            if ($node->filter('.fa-arrow-alt-circle-right')->count() > 0 && $page === null) {
                $lastPageUrl = $absolute;
            }

            if ($page !== null) {
                $pageLinks[$page] = $absolute;
            }

            if ($page !== null && $page === $this->pageFromLabel($label)) {
                return;
            }

            if (in_array($label, ['»', '›', '>'], true) || strcasecmp($label, 'next') === 0) {
                if (in_array($label, ['›', '>'], true) || strcasecmp($label, 'next') === 0) {
                    $explicitNextUrl = $absolute;
                } else {
                    $lastPageUrl = $absolute;
                }
            }
        });

        $crawler->filter(Selectors::PAGINATION_CURRENT)->each(function (Crawler $node) use (&$currentPage): void {
            $value = trim($node->text(''));
            if (is_numeric($value) && (int) $value > 0) {
                $currentPage = (int) $value;
            }
        });

        $lastPage = $lastPageUrl !== null ? $this->pageFromUrl($lastPageUrl, $url) : null;
        $visiblePages = array_keys($pageLinks);
        if ($lastPage === null && $visiblePages !== []) {
            $lastPage = max([$currentPage, ...$visiblePages]);
        }

        $greaterPages = array_values(array_filter($visiblePages, fn(int $page): bool => $page > $currentPage));
        sort($greaterPages);
        $nextPage = $greaterPages[0] ?? null;
        $nextUrl = $nextPage === null ? null : ($pageLinks[$nextPage] ?? null);

        if ($nextUrl === null && $explicitNextUrl !== null) {
            $nextUrl = $explicitNextUrl;
            $nextPage = $this->pageFromUrl($explicitNextUrl, $url) ?? ($currentPage + 1);
        }

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $lastPage,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null && $currentPage < ($lastPage ?? $nextPage),
        );
    }

    private function pageFromUrl(string $url, string $listingUrl): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        $listingPath = parse_url($listingUrl, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#^/r18/(\d+)/?$#', $path, $match) === 1) {
            return (int) $match[1];
        }

        if (! is_string($listingPath)) {
            return null;
        }

        $listingBase = trim($listingPath, '/');
        if ($listingBase === '' || $listingBase === 'r18') {
            return null;
        }

        $pattern = '#^/' . preg_quote($listingBase, '#') . '/(\d+)/?$#i';
        if (preg_match($pattern, $path, $match) === 1) {
            $page = (int) $match[1];

            return $page > 0 ? $page : null;
        }

        return null;
    }

    private function pageFromLabel(string $label): ?int
    {
        return is_numeric($label) && (int) $label > 0 ? (int) $label : null;
    }

    private function cardDurationLabel(Crawler $node): ?string
    {
        if ($node->filter('b')->count() === 0) {
            $text = $this->normalizeText($node->text(''));
            if ($text !== null && preg_match('/(\d{1,2}:\d{2})/', $text, $match) === 1) {
                return $match[1];
            }

            return null;
        }

        foreach ($node->filter('b') as $bold) {
            $text = $this->normalizeText($bold->textContent ?? '');
            if ($text !== null && preg_match('/^\d{1,2}:\d{2}$/', $text) === 1) {
                return $text;
            }
        }

        return null;
    }
}
