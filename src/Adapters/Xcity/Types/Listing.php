<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Xcity\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractType implements TypeInterface
{
    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'XCity', 'listing');

        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter('.x-itemBox a[href*="/avod/detail/"]')->each(function (Crawler $node) use (&$items, $request): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $absolute = $this->normalizer->absolute($request->url, $href);
            $externalId = $this->externalId($absolute);
            if ($externalId === null) {
                return;
            }

            $items[$absolute] = MovieDto::item(
                url: $absolute,
                externalId: $externalId,
                title: $this->itemTitle($node),
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
        $next = $crawler->filter('.pageScrl li.next a[href*="page="], .pageScrl a[rel="next"][href*="page="]');
        $nextUrl = null;
        if ($next->count() > 0) {
            $href = $next->first()->attr('href');
            $nextUrl = is_string($href) && trim($href) !== '' ? $this->normalizer->absolute($url, $href) : null;
        }

        $nextPage = $nextUrl !== null ? $this->pageFromUrl($nextUrl) : null;

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: null,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function externalId(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $id = $params['id'] ?? null;

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    private function itemTitle(Crawler $node): ?string
    {
        foreach ([$node->attr('title'), $node->filter('img[alt]')->count() > 0 ? $node->filter('img[alt]')->first()->attr('alt') : null, $node->text('')] as $value) {
            if (! is_string($value)) {
                continue;
            }

            $text = $this->cleanText($value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
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

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));
    }
}
