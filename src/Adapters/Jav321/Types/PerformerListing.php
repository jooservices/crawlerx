<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jav321\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Jav321\Concerns\ParsesMovieCards;
use JOOservices\CrawlerX\Adapters\Jav321\Selectors;
use JOOservices\CrawlerX\Adapters\Jav321\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerListing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;
    use ParsesMovieCards;

    public function __construct(
        CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return 'Jav321';
    }

    protected function contextLabel(): string
    {
        return 'performer_listing';
    }

    protected function urlNormalizer(): UrlNormalizer
    {
        return $this->normalizer;
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::PERFORMER_LINKS)->each(function (Crawler $link) use (&$items, $request): void {
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->normalizer->absoluteAndCanonical($request->url, $href);
            $externalId = $this->performerExternalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->firstText($link, Selectors::PERFORMER_NAME),
                data: MovieMeta::filter([
                    'profile_image_url' => $this->cardImage($link, $request->url),
                ]),
            );
        });

        if ($items === []) {
            throw new \JOOservices\CrawlerX\Exceptions\CrawlParseException('Jav321 performer listing page did not contain expected performer fields.');
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
        $nextUrl = null;
        $href = $this->firstAttribute($crawler, Selectors::NEXT_LINK, 'href');
        if ($href !== null) {
            $nextUrl = $this->normalizer->absoluteAndCanonical($url, $href);
        }

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: null,
            nextPage: $nextUrl === null ? null : $this->pageFromUrl($nextUrl),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function performerExternalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match('#^/star/(\d+)/#', $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function pageFromUrl(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match('#/(\d+)/?$#', $path, $match) !== 1) {
            return null;
        }

        $page = (int) $match[1];

        return $page > 0 ? $page : null;
    }
}
