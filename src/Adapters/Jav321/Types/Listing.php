<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jav321\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ReadsPathNumbers;
use JOOservices\CrawlerX\Adapters\Jav321\Concerns\ParsesMovieCards;
use JOOservices\CrawlerX\Adapters\Jav321\Selectors;
use JOOservices\CrawlerX\Adapters\Jav321\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ReadsPathNumbers;
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
        return 'listing';
    }

    protected function urlNormalizer(): UrlNormalizer
    {
        return $this->normalizer;
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::MOVIE_CARDS)->each(function (Crawler $link) use (&$items, $request): void {
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->normalizer->absoluteAndCanonical($request->url, $href);
            $externalId = $this->movieExternalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            [$title, $code] = $this->splitTitleCode($this->normalizeText($link->text('')));

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: MovieMeta::filter([
                    'code' => $code,
                    'cover_url' => $this->cardImage($link, $request->url),
                    'metadata' => MovieMeta::filter([
                        'has_download' => $this->hasDownloadBadge($link),
                    ]),
                ]),
            );
        });

        if ($items === []) {
            throw new \JOOservices\CrawlerX\Exceptions\CrawlParseException('Jav321 listing page did not contain expected movie fields.');
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
        $nextUrl = null;
        $href = $this->firstAttribute($crawler, Selectors::NEXT_LINK, 'href');
        if ($href !== null) {
            $nextUrl = $this->normalizer->absoluteAndCanonical($url, $href);
        }

        $nextPage = $nextUrl === null ? null : $this->pageFromUrl($nextUrl);

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: null,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function pageFromUrl(string $url): ?int
    {
        return $this->pathNumber($url, '#/(\d+)/?$#');
    }
}
