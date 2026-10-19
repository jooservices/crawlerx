<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Duga\Types;

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

/** DUGA product cards: title, jacket, rating, my-list count, lowest price and the new-release flag. */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    // The home page (/main/) fills its product cards with JavaScript; the ranking page has them in the HTML.
    private const ROOT_URL = 'https://duga.jp/ranking/';

    protected function siteLabel(): string
    {
        return 'DUGA';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $path = parse_url($request->url, PHP_URL_PATH);
        $fetchUrl = $path === null || $path === '' || $path === '/' || $path === '/main/' ? self::ROOT_URL : $request->url;
        $crawler = $this->fetchCrawler(new CrawlRequestDto(url: $fetchUrl, type: $request->type, page: $request->page));
        $items = [];

        $crawler->filter('.contentslist:not(.empty), .contentslistrank')->each(function (Crawler $card) use (&$items, $fetchUrl): void {
            $href = $this->firstAttribute($card, 'a[href^="/ppv/"]', 'href');
            if ($href === null || preg_match('~^/ppv/([^/]+)/?~', $href, $match) !== 1) {
                return;
            }

            $url = $this->absolute($fetchUrl, '/ppv/' . $match[1] . '/');
            if (isset($items[$url])) {
                return;
            }

            $cover = $this->firstAttribute($card, '.image img', 'src');
            $money = $this->firstText($card, '.money');
            $amount = $money !== null && preg_match('/([\d,]+)/', $money, $moneyMatch) === 1 ? (float) str_replace(',', '', $moneyMatch[1]) : null;
            $score = $this->firstAttribute($card, '.ratingstar img', 'alt');
            if ($score === null || preg_match('/\d/', $score) !== 1) {
                // The alt text is "Rating-Score" on some pages; the star image name carries the value (score/4.0.gif).
                $src = $this->firstAttribute($card, '.ratingstar img', 'src');
                $score = $src !== null && preg_match('~/score/(\d+(?:\.\d+)?)\.gif~', $src, $scoreSrc) === 1 ? $scoreSrc[1] : null;
            }
            $rank = $this->firstText($card, '.rankno');
            $favorites = $this->firstText($card, '.mylist');

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $match[1],
                title: $this->firstAttribute($card, '.image img', 'alt') ?? $this->firstText($card, '.title') ?? $match[1],
                data: MovieMeta::filter([
                    'code' => $match[1],
                    'cover_url' => $cover === null ? null : $this->absolute($fetchUrl, $cover),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::RATING => $score !== null && preg_match('/(\d+(?:\.\d+)?)/', $score, $scoreMatch) === 1 && (float) $scoreMatch[1] > 0 ? MovieMeta::rating((float) $scoreMatch[1], 5.0) : null,
                        MovieMeta::FAVORITE_COUNT => $favorites !== null && ctype_digit(str_replace(',', '', $favorites)) ? (int) str_replace(',', '', $favorites) : null,
                        MovieMeta::PRICE => $amount === null ? null : MovieMeta::price($amount, 'JPY', $money),
                        'flags' => $this->texts($card, '.status span'),
                        'rank' => $rank !== null && preg_match('/(\d+)/', $rank, $rankMatch) === 1 ? (int) $rankMatch[1] : null,
                        'previous_rank' => $this->previousRank($card),
                    ]),
                ]),
            );
        });

        if ($items === []) {
            throw new CrawlParseException('DUGA listing page did not contain product cards.');
        }

        $next = $this->firstAttribute($crawler, 'a[rel="next"][href], .pager a.next[href]', 'href');
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

    private function previousRank(Crawler $card): ?int
    {
        $text = $this->firstText($card, '.rankprev');

        return $text !== null && preg_match('/前週\s*(\d+)\s*位/u', $text, $match) === 1 ? (int) $match[1] : null;
    }
}
