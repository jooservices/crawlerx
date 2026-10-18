<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDb\Types;

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

/** JavDB movie cards: code, title, cover, score and votes, release date (month first) and the DL/Today flags. */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const ROOT_URL = 'https://javdb.com/';

    protected function siteLabel(): string
    {
        return 'JavDB';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $path = parse_url($request->url, PHP_URL_PATH);
        $fetchUrl = $path === null || $path === '' || $path === '/' ? ($request->url === '' ? self::ROOT_URL : $request->url) : $request->url;
        $crawler = $this->fetchCrawler(new CrawlRequestDto(url: $fetchUrl, type: $request->type, page: $request->page));
        $items = [];

        $crawler->filter('.movie-list .item')->each(function (Crawler $card) use (&$items, $fetchUrl): void {
            $href = $this->firstAttribute($card, 'a.box[href^="/v/"]', 'href') ?? $this->firstAttribute($card, 'a[href^="/v/"]', 'href');
            if ($href === null) {
                return;
            }

            $url = $this->canonical($this->absolute($fetchUrl, $href));
            if (isset($items[$url])) {
                return;
            }

            $code = $this->firstText($card, '.video-title strong');
            $title = $this->firstAttribute($card, 'a.box', 'title')
                ?? $this->normalizeText(str_replace((string) $code, '', (string) $this->firstText($card, '.video-title')))
                ?? $code;
            $cover = $this->firstAttribute($card, '.cover img', 'src');
            $score = $this->firstText($card, '.score .value');
            $rating = $score !== null && preg_match('/(\d+(?:\.\d+)?)\s*,\s*by\s+([\d,]+)\s+users?/i', $score, $match) === 1
                ? MovieMeta::rating((float) $match[1], 5.0, (int) str_replace(',', '', $match[2]))
                : null;
            $dateText = $this->firstText($card, '.meta');

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: basename((string) parse_url($url, PHP_URL_PATH)),
                title: $title,
                data: MovieMeta::filter([
                    'code' => $code,
                    'cover_url' => $cover === null ? null : $this->absolute($fetchUrl, $cover),
                    'date' => MovieMeta::isoDateMonthFirst($dateText),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::RATING => $rating,
                        MovieMeta::RELEASE_DATE_RAW => $dateText,
                        'flags' => $this->texts($card, '.tags .tag'),
                    ]),
                ]),
            );
        });

        if ($items === []) {
            throw new CrawlParseException('JavDB listing page did not contain movie cards.');
        }

        $next = $this->firstAttribute($crawler, 'a.pagination-next[href], a[rel="next"][href]', 'href');
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

    private function canonical(string $url): string
    {
        $parts = parse_url($url);

        return is_array($parts) && isset($parts['scheme'], $parts['host'], $parts['path']) ? $parts['scheme'] . '://' . $parts['host'] . $parts['path'] : $url;
    }
}
