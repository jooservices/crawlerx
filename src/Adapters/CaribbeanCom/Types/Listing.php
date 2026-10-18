<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/** Caribbeancom movie cards: title, thumbnail, release date and the starring performers. */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const ROOT_URL = 'https://en.caribbeancom.com/eng/index2.htm';

    protected function siteLabel(): string
    {
        return 'Caribbeancom';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $path = parse_url($request->url, PHP_URL_PATH);
        $fetchUrl = $path === null || $path === '' || $path === '/' ? self::ROOT_URL : $request->url;
        $crawler = $this->fetchCrawler(new CrawlRequestDto(url: $fetchUrl, type: $request->type, page: $request->page));
        $items = [];

        $crawler->filter('.grid-item .entry')->each(function (Crawler $card) use (&$items, $fetchUrl): void {
            $href = $this->firstAttribute($card, '.meta-title a[href]', 'href') ?? $this->firstAttribute($card, 'a[href*="/moviepages/"]', 'href');
            if ($href === null || preg_match('~/moviepages/([^/]+)/~i', $href, $match) !== 1) {
                return;
            }

            $url = $this->absolute($fetchUrl, $href);
            if (isset($items[$url])) {
                return;
            }

            $cover = $this->firstAttribute($card, '.media-thum img', 'src');
            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $match[1],
                title: $this->firstText($card, '.meta-title a') ?? $this->firstAttribute($card, '.media-thum img', 'title') ?? $match[1],
                data: MovieMeta::filter([
                    'code' => $match[1],
                    'cover_url' => $cover === null ? null : $this->absolute($fetchUrl, $cover),
                    'date' => MovieMeta::isoDate($this->firstText($card, '.meta-data')),
                    'performers' => $this->performers($card, $fetchUrl),
                ]),
            );
        });

        if ($items === []) {
            throw new CrawlParseException('Caribbeancom listing page did not contain movie cards.');
        }

        $next = $this->firstAttribute($crawler, 'a[rel="next"][href], .pagination a.next[href]', 'href');
        $nextUrl = $next === null ? null : $this->absolute($fetchUrl, $next);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: $nextUrl === null ? null : $request->page + 1,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    private function performers(Crawler $card, string $baseUrl): array
    {
        $performers = [];
        $card->filter('a[itemprop="actor"]')->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = (string) $link->attr('href');
            if ($name === null || $href === '' || isset($performers[$href])) {
                return;
            }

            $id = preg_match('~/search_act/(\d+)/~', $href, $match) === 1 ? $match[1] : null;
            $performers[$href] = MovieMeta::performer($name, $id, $this->absolute($baseUrl, $href));
        });

        return array_values($performers);
    }
}
