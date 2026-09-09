<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Selectors;
use JOOservices\CrawlerX\Adapters\MinnanoAv\UrlNormalizer;
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

    private readonly UrlNormalizer $normalizer;

    public function __construct(CrawlHttpClient $client)
    {
        parent::__construct($client);
        $this->normalizer = new UrlNormalizer();
    }

    protected function siteLabel(): string
    {
        return 'minnanoav';
    }

    protected function contextLabel(): string
    {
        return 'filmography_listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::FILMOGRAPHY_MOVIE_LINKS)->each(function (Crawler $node) use (&$items, $request): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            if (preg_match('#av\d+\.html$#i', $href) !== 1) {
                return;
            }

            $url = $this->normalizer->absolute($request->url, $href);
            $title = $this->normalizeText($node->text('')) ?? '';
            if ($title === '') {
                return;
            }

            $externalId = $this->externalIdFromAvUrl($url);
            $code = CodeNormalizer::canonical($title, $externalId, $url);

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: array_filter(['code' => $code], fn(mixed $value): bool => $value !== null && $value !== ''),
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
        $nextUrl = null;
        $crawler->filter(Selectors::PAGINATION_NEXT)->each(function (Crawler $node) use (&$nextUrl, $url): void {
            if ($nextUrl !== null) {
                return;
            }

            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $nextUrl = $this->normalizer->absolute($url, $href);
        });

        if ($nextUrl === null) {
            $crawler->filter('.pagination a[href*="page="]')->each(function (Crawler $node) use (&$nextUrl, $url, $currentPage): void {
                $href = $node->attr('href');
                if (! is_string($href) || preg_match('/page=(\d+)/', $href, $match) !== 1) {
                    return;
                }

                if ((int) $match[1] === $currentPage + 1) {
                    $nextUrl = $this->normalizer->absolute($url, $href);
                }
            });
        }

        $nextPage = $nextUrl !== null ? $this->pageFromUrl($nextUrl) : null;

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: null,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null,
        );
    }

    private function externalIdFromAvUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/(av\d+)\.html$#i', $path, $match) === 1) {
            return strtolower($match[1]);
        }

        return null;
    }

    private function pageFromUrl(string $url): ?int
    {
        if (preg_match('/[?&]page=(\d+)/', $url, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }
}
