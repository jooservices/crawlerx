<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavLibrary\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavLibrary\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerListing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'JavLibrary';
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
            $name = $this->performerName($node);
            if (! is_string($href) || trim($href) === '' || $name === null) {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $externalId = $this->externalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $name,
            );
        });

        $nextUrl = $this->nextPageUrl($crawler, $request->url);
        $lastPage = $this->lastPageNumber($crawler, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: $lastPage,
                nextPage: $nextUrl !== null ? $this->pageNumber($nextUrl) : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function performerName(Crawler $node): ?string
    {
        $title = $node->attr('title');
        if (is_string($title) && trim($title) !== '') {
            return $this->normalizeText($title);
        }

        $text = $this->normalizeText($node->text(''));
        if ($text !== null) {
            return $text;
        }

        $parent = $node->ancestors()->eq(0);
        if ($parent->count() > 0) {
            $nameNode = $parent->filter('.star_name a');
            if ($nameNode->count() > 0) {
                return $this->normalizeText($nameNode->first()->text(''));
            }
        }

        return null;
    }

    private function nextPageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter('div.page_selector a.page.next');
        if ($node->count() === 0) {
            return null;
        }

        $href = $node->first()->attr('href');
        if (! is_string($href) || trim($href) === '') {
            return null;
        }

        return $this->absolute($baseUrl, $href);
    }

    private function lastPageNumber(Crawler $crawler, string $baseUrl): ?int
    {
        $node = $crawler->filter(Selectors::PAGINATION_LAST);
        if ($node->count() === 0) {
            return null;
        }

        $href = $node->first()->attr('href');
        if (! is_string($href) || trim($href) === '') {
            return null;
        }

        return $this->pageNumber($this->absolute($baseUrl, $href));
    }

    private function pageNumber(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $page = $params['page'] ?? null;

        return is_numeric($page) && (int) $page > 0 ? (int) $page : null;
    }

    private function externalId(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $id = $params['st'] ?? $params['s'] ?? null;

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }
}
