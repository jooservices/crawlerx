<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Heyzo\Types;

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

/**
 * HEYZO movie cards. A card carries no movie title (the image text is the actress name): thumbnail, duration (in the
 * hover data), performer type, release date, actress and review stars.
 */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const ROOT_URL = 'https://en.heyzo.com/listpages/all_1.html';

    protected function siteLabel(): string
    {
        return 'HEYZO';
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

        $crawler->filter('.movie')->each(function (Crawler $card) use (&$items, $fetchUrl): void {
            $link = $card->filter('a[href*="/moviepages/"]');
            $href = $link->count() > 0 ? (string) $link->first()->attr('href') : '';
            if (preg_match('~/moviepages/(\d+)/~', $href, $match) !== 1) {
                return;
            }

            $url = $this->absolute($fetchUrl, $href);
            if (isset($items[$url])) {
                return;
            }

            $thumb = $this->firstAttribute($card, 'img.lazy, img', 'data-original') ?? $this->firstAttribute($card, 'img', 'src');
            $release = $this->firstText($card, 'p.release');
            $actorLink = $card->filter('a.actor');
            $actorHref = $actorLink->count() > 0 ? (string) $actorLink->first()->attr('href') : '';
            $actorName = $actorLink->count() > 0 ? $this->normalizeText($actorLink->first()->text('')) : null;
            $actorId = preg_match('~actor_(\d+)_~', $actorHref, $actorMatch) === 1 ? $actorMatch[1] : null;
            $hover = (string) $link->first()->attr('onmouseover');

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $match[1],
                title: null,
                data: MovieMeta::filter([
                    'code' => 'HEYZO-' . $match[1],
                    'cover_url' => $thumb === null ? null : $this->absolute($fetchUrl, $thumb),
                    'date' => MovieMeta::isoDate($release),
                    'duration' => preg_match('/"full":"([\d:]+)"/', html_entity_decode($hover), $time) === 1 ? MovieMeta::durationMinutes($time[1]) : null,
                    'performers' => $actorName === null ? [] : [MovieMeta::performer($actorName, $actorId, $this->absolute($fetchUrl, explode('?', $actorHref)[0]))],
                    'metadata' => MovieMeta::filter([
                        'performer_type' => $this->performerType($card),
                        MovieMeta::RELEASE_DATE_RAW => $release === null ? null : trim(str_replace('Release:', '', $release)),
                    ]),
                ]),
            );
        });

        if ($items === []) {
            throw new CrawlParseException('HEYZO listing page did not contain movie cards.');
        }

        $next = $this->firstAttribute($crawler, 'link[rel="next"][href], li.next a[href]', 'href');
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

    /** "Porn Star", "Amateur"... the label printed in front of the release date. */
    private function performerType(Crawler $card): ?string
    {
        $label = $card->filter('.actor-type-av, .icon');
        if ($label->count() === 0) {
            return null;
        }

        $text = $this->normalizeText($label->first()->text(''));

        return $text === null ? null : trim((string) preg_replace('/Release:.*$/u', '', $text));
    }
}
