<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fc2\Types;

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

/** FC2 product cards: title, thumbnail, duration, price and discount, stock, favourites, seller. */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const ROOT_URL = 'https://adult.contents.fc2.com/';

    protected function siteLabel(): string
    {
        return 'FC2 Content Market';
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

        $crawler->filter('.c-neoItem-1000_wrap, .c-neoItem-1000L_wrap')->each(function (Crawler $card) use (&$items, $fetchUrl): void {
            $href = $this->firstAttribute($card, 'a[href^="/article/"]', 'href');
            if ($href === null || preg_match('~^/article/(\d+)~', $href, $match) !== 1) {
                return;
            }

            $url = $this->absolute($fetchUrl, '/article/' . $match[1] . '/');
            if (isset($items[$url])) {
                return;
            }

            $thumb = $this->firstAttribute($card, '.c-cntCard-110-f_thumb img', 'src');
            $listPrice = $this->firstText($card, '.c-cntCard-110-f_price');
            $salePrice = $this->firstText($card, '.c-cntCard-110_priceDown');
            $current = $this->points($salePrice) ?? $this->points($listPrice);
            $discount = $this->firstText($card, '.c-cntCard-110-f_thumbLabels [class^="sale_tag"]');
            $stock = $this->firstText($card, '.c-cntCard-110-f_limited');
            $left = $this->firstText($card, '.remain-deal-days');
            $sellerHref = $this->firstAttribute($card, '.c-cntCard-110-f_ownerName a', 'href');

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $match[1],
                title: $this->firstAttribute($card, 'a.c-cntCard-110-f_thumb_link', 'title') ?? $match[1],
                data: MovieMeta::filter([
                    'code' => 'FC2-PPV-' . $match[1],
                    'cover_url' => $thumb === null ? null : $this->absolute($fetchUrl, $thumb),
                    'duration' => MovieMeta::durationMinutes($this->firstText($card, '.c-cntCard-110-f_thumb_num')),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::PRICE => $current === null ? null : MovieMeta::price($current, 'pt', $salePrice ?? $listPrice),
                        'list_price' => $salePrice !== null && $this->points($listPrice) !== null ? MovieMeta::price($this->points($listPrice), 'pt', $listPrice) : null,
                        'discount_percent' => $discount !== null && preg_match('/(\d+)/', $discount, $d) === 1 ? (int) $d[1] : null,
                        'remaining' => $stock !== null && preg_match('/(\d+)/', $stock, $s) === 1 ? (int) $s[1] : null,
                        'sale_days_left' => $left !== null && preg_match('/(\d+)/', $left, $l) === 1 ? (int) $l[1] : null,
                        MovieMeta::FAVORITE_COUNT => $this->integer($this->firstText($card, '.c-cntCard-110-f_MoreInfoFav b')),
                        'recommend_count' => $this->integer($this->firstText($card, '.c-cntCard-110-f_recom')),
                        MovieMeta::COMMENT_COUNT => $this->integer($this->firstText($card, '.c-cntCard-110-f_comment')),
                        MovieMeta::SELLER => $this->firstText($card, '.c-cntCard-110-f_ownerName a'),
                        'seller_url' => $sellerHref,
                        'promoted' => str_contains((string) $this->firstText($card, '.c-cntCard-110-f_itemName'), '[PR]') ? true : null,
                    ]),
                ]),
            );
        });

        if ($items === []) {
            throw new CrawlParseException('FC2 Content Market listing page did not contain product cards.');
        }

        $next = $this->firstAttribute($crawler, 'a[rel="next"][href], .c-pagenation a.next[href]', 'href');
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

    private function points(?string $text): ?float
    {
        return $text !== null && preg_match('/([\d,]+)\s*pt/i', $text, $match) === 1 ? (float) str_replace(',', '', $match[1]) : null;
    }

    private function integer(?string $text): ?int
    {
        return $text !== null && ctype_digit(str_replace(',', '', $text)) ? (int) str_replace(',', '', $text) : null;
    }
}
