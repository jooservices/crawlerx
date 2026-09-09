<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavBus\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavBus\Selectors;
use JOOservices\CrawlerX\Adapters\JavBus\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerListing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return 'JavBus';
    }

    protected function contextLabel(): string
    {
        return 'performer_listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::PERFORMER_LINKS)->each(function (Crawler $node) use (&$items, $request): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->normalizer->absoluteAndCanonical($request->url, $href);
            $externalId = $this->performerExternalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $name = $this->performerName($node);

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $name,
            );
        });

        if ($items === []) {
            throw new \RuntimeException('JavBus performer listing page did not contain expected performer fields.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
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

            $absolute = $this->normalizer->absoluteAndCanonical($url, $href);
            $label = strtolower(trim($node->text('')));
            $page = $this->pageFromUrl($absolute);

            if ($label === '»' || $label === 'next' || $node->attr('id') === 'next') {
                $explicitNextUrl = $absolute;
            }

            if ($page !== null) {
                $pageLinks[$page] = $absolute;
            }
        });

        $visiblePages = array_keys($pageLinks);
        $lastPage = $visiblePages === [] ? null : max($visiblePages);
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

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $lastPage,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null,
        );
    }

    private function performerName(Crawler $node): ?string
    {
        $parent = $node->ancestors()->filter('.movie-box')->first();
        if ($parent->count() > 0 && $parent->filter('.photo-info span')->count() > 0) {
            return $this->normalizeText($parent->filter('.photo-info span')->first()->text(''));
        }

        if ($node->filter('img[title]')->count() > 0) {
            $title = $node->filter('img[title]')->first()->attr('title');
            if (is_string($title) && trim($title) !== '') {
                return $this->normalizeText($title);
            }
        }

        return $this->normalizeText($node->text(''));
    }

    private function performerExternalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/(?:en/)?star/([^/]+)/?$#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function pageFromUrl(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#/(?:en/)?(?:stars/page|actresses)/(\d+)/?$#i', $path, $match) === 1) {
            $page = (int) $match[1];

            return $page > 0 ? $page : null;
        }

        if (preg_match('#/(?:en/)?(?:stars|actresses)/?$#i', $path) === 1) {
            return 1;
        }

        return null;
    }
}
