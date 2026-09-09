<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnejavTheme;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class TagActressListing extends AbstractHtmlType implements TypeInterface
{
    public function __construct(
        CrawlHttpClient $client,
        private readonly string $siteLabel = 'onejav',
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return $this->siteLabel;
    }

    protected function contextLabel(): string
    {
        return 'tag_listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $names = $this->parseNames($crawler);
        $pagination = $this->parsePagination($crawler, $request->url);

        $items = array_map(fn(string $name): CrawlItemResultDto => MovieDto::item(
            url: $request->url,
            externalId: $name,
            title: $name,
        ), $names);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: $pagination['nextPage'],
                nextUrl: $pagination['nextUrl'],
                hasNextPage: $pagination['hasNextPage'],
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function parseNames(Crawler $crawler): array
    {
        $names = [];

        $crawler->filter('a.tag.is-light[href]')->each(function (Crawler $node) use (&$names): void {
            $href = $node->attr('href');
            if (! is_string($href) || (! str_contains($href, '/tag/') && ! str_contains($href, '/actress/'))) {
                return;
            }

            $name = trim(urldecode(basename(rtrim($href, '/'))));
            if ($name !== '') {
                $names[] = $name;
            }
        });

        return array_values(array_unique($names));
    }

    /**
     * @return array{hasNextPage: bool, nextPage: ?int, nextUrl: ?string}
     */
    private function parsePagination(Crawler $crawler, string $baseUrl): array
    {
        $none = ['hasNextPage' => false, 'nextPage' => null, 'nextUrl' => null];

        $currentNode = $crawler->filter('a.pagination-link.button.is-primary:not(.is-inverted)');
        if ($currentNode->count() === 0) {
            return $none;
        }

        $currentPage = (int) trim($currentNode->first()->text('0'));
        $pageLinks = [];

        $crawler->filter('a.pagination-link.button.is-primary.is-inverted')->each(function (Crawler $node) use (&$pageLinks, $baseUrl): void {
            $page = (int) trim($node->text('0'));
            $href = $node->attr('href');
            if ($page > 0 && is_string($href) && $href !== '') {
                $pageLinks[$page] = UriResolver::resolve($href, $baseUrl);
            }
        });

        $visiblePages = array_keys($pageLinks);
        $greaterPages = array_values(array_filter($visiblePages, fn(int $p): bool => $p > $currentPage));
        sort($greaterPages);
        $nextPage = $greaterPages === [] ? null : $greaterPages[0];

        if ($nextPage === null) {
            return $none;
        }

        return [
            'hasNextPage' => true,
            'nextPage' => $nextPage,
            'nextUrl' => $pageLinks[$nextPage],
        ];
    }
}
