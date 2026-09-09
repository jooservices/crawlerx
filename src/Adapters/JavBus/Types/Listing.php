<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavBus\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavBus\Selectors;
use JOOservices\CrawlerX\Adapters\JavBus\UrlNormalizer;
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
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->listingCrawler($request);
        $items = [];

        $crawler->filter(Selectors::MOVIE_BOXES)->each(function (Crawler $box) use (&$items, $request): void {
            if ($box->filter('a[href]')->count() === 0) {
                return;
            }

            $link = $box->filter('a[href]')->first();
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->normalizer->absoluteAndCanonical($request->url, $href);
            $externalId = $this->movieExternalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $rawTitle = $this->movieTitle($box, $link);
            $code = CodeNormalizer::canonical($rawTitle, $externalId, $url) ?? strtoupper($externalId);

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->titleWithoutCode($rawTitle, $code),
                data: [
                    'code' => $code,
                    'cover_url' => $this->coverUrl($box, $request->url),
                ],
            );
        });

        if ($items === []) {
            throw new \JOOservices\CrawlerX\Exceptions\CrawlParseException('JavBus listing page did not contain expected movie fields.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->parsePagination($crawler, $request->url, $request->page),
        );
    }

    private function listingCrawler(CrawlRequestDto $request): Crawler
    {
        $path = parse_url($request->url, PHP_URL_PATH);
        if ($path !== null && $path !== '' && $path !== '/') {
            return $this->fetchCrawler($request);
        }

        $acceptedUrl = 'https://www.javbus.com/en/';
        $response = $this->client->get($acceptedUrl);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'JavBus', 'listing');

        return new Crawler($html, $acceptedUrl);
    }

    private function parsePagination(Crawler $crawler, string $url, int $currentPage): CrawlPaginationDto
    {
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

            $absolute = $this->normalizer->absoluteAndCanonical($url, $href);
            $label = strtolower(trim($node->text('')));
            $page = $this->pageFromUrl($absolute);

            if ($label === '»' || $label === 'next' || $node->attr('id') === 'next') {
                $explicitNextUrl = $absolute;
            }

            if ($label === '»' && $page !== null) {
                $lastPageUrl = $absolute;
            }

            if ($page !== null) {
                $pageLinks[$page] = $absolute;
            }
        });

        $lastPage = $lastPageUrl !== null ? $this->pageFromUrl($lastPageUrl) : null;
        $visiblePages = array_keys($pageLinks);
        if ($lastPage === null && $visiblePages !== []) {
            $lastPage = max([$currentPage, ...$visiblePages]);
        }

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
            hasNextPage: $nextPage !== null && $currentPage < ($lastPage ?? $nextPage),
        );
    }

    private function movieTitle(Crawler $box, Crawler $link): ?string
    {
        if ($box->filter('img[title]')->count() > 0) {
            $title = $box->filter('img[title]')->first()->attr('title');
            if (is_string($title) && trim($title) !== '') {
                return $this->normalizeText($title);
            }
        }

        $title = $link->attr('title');

        return is_string($title) && trim($title) !== '' ? $this->normalizeText($title) : $this->normalizeText($link->text(''));
    }

    private function coverUrl(Crawler $box, string $baseUrl): ?string
    {
        if ($box->filter('img')->count() === 0) {
            return null;
        }

        $src = $box->filter('img')->first()->attr('src');

        return is_string($src) && trim($src) !== ''
            ? $this->normalizer->absoluteAndCanonical($baseUrl, $src)
            : null;
    }

    private function movieExternalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#/(?:en/)?(?:star|stars|page|genre|studio|search|uncensored|censored|director|label|series)(?:/|$)#i', $path) === 1) {
            return null;
        }

        if (preg_match('#^/(?:en/)?([A-Za-z0-9-]+)/?$#i', $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function titleWithoutCode(?string $title, string $code): ?string
    {
        if ($title === null) {
            return null;
        }

        $stripped = preg_replace('/^' . preg_quote($code, '/') . '\s*/i', '', $title);

        return is_string($stripped) && trim($stripped) !== '' ? trim($stripped) : $title;
    }

    private function pageFromUrl(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#/(?:en/)?page/(\d+)/?$#i', $path, $match) === 1) {
            $page = (int) $match[1];

            return $page > 0 ? $page : null;
        }

        if (preg_match('#/(?:en/)?stars(?:/page/(\d+))?/?$#i', $path, $match) === 1) {
            $page = isset($match[1]) ? (int) $match[1] : 1;

            return $page > 0 ? $page : null;
        }

        return null;
    }
}
