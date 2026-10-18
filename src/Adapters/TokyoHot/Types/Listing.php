<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TokyoHot\Types;

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
 * Tokyo-Hot cards, on the home page ("New videos" grid) and on `/product/` (`li.detail`, with page numbers): product
 * id, title (some cards have none), short description and thumbnail. Navigation menus also link to `/product/`
 * filters; only real product links are cards.
 */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'Tokyo-Hot';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter('li.detail, .new li, .grid li')->each(function (Crawler $card) use (&$items, $request): void {
            $link = $card->filter('a[href^="/product/"]');
            $href = $link->count() > 0 ? (string) $link->first()->attr('href') : '';
            if (preg_match('~^/product/([^/?#]+)/?$~', $href, $match) !== 1) {
                return;
            }

            $url = $this->absolute($request->url, '/product/' . $match[1] . '/');
            if (isset($items[$url])) {
                return;
            }

            $thumb = $this->firstAttribute($card, 'img', 'data-original');
            $thumb = $thumb !== null && $thumb !== '' ? $thumb : $this->firstAttribute($card, 'img', 'src');
            $title = $this->firstText($card, '.title');

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $match[1],
                title: $title ?? $match[1],
                data: MovieMeta::filter([
                    'code' => $match[1],
                    'cover_url' => $thumb === null || str_contains($thumb, '55x31.png') ? null : $this->absolute($request->url, $thumb),
                    'description' => $this->firstText($card, '.text'),
                ]),
            );
        });

        if ($items === []) {
            throw new CrawlParseException('Tokyo-Hot listing page did not contain product cards.');
        }

        $next = $this->firstAttribute($crawler, '.navi li.next a[href]', 'href');
        $nextUrl = $next === null ? null : $this->absolute($request->url, html_entity_decode($next));
        $end = $this->firstAttribute($crawler, '.navi .naviend a[href]', 'href');
        $last = $end !== null && preg_match('/page=(\d+)/', $end, $page) === 1 ? (int) $page[1] : null;

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: $last,
                nextPage: $nextUrl === null ? null : $request->page + 1,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }
}
