<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Mgstage\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ReadsQueryNumbers;
use JOOservices\CrawlerX\Adapters\Mgstage\Concerns\ParsesProductData;
use JOOservices\CrawlerX\Adapters\Mgstage\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;
    use ReadsQueryNumbers;
    use ParsesProductData;

    protected function siteLabel(): string
    {
        return 'MGStage';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$items, $request): void {
            $item = $this->card($card, $request->url);
            if ($item !== null && ! isset($items[$item->url])) {
                $items[$item->url] = $item;
            }
        });

        if ($items === []) {
            throw new CrawlParseException('MGStage listing page did not contain expected movie fields.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request),
        );
    }

    private function card(Crawler $card, string $pageUrl): ?CrawlItemResultDto
    {
        $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
        $url = $href === null ? null : $this->absolute($pageUrl, $href);
        $sku = $url === null ? null : $this->skuFromUrl($url);
        if ($url === null || $sku === null) {
            return null;
        }

        $thumbnail = $this->firstAttribute($card, Selectors::CARD_COVER, 'src');
        $images = $this->sampleImages($card, $pageUrl);
        $largeCover = $images['cover'];
        $player = $this->firstAttribute($card, Selectors::CARD_SAMPLE_PLAYER, 'href');
        $originPrice = $this->yenFromText($this->firstText($card, Selectors::CARD_ORIGIN_PRICE));
        $minPrice = $this->yenFromText($this->firstText($card, Selectors::CARD_MIN_PRICE));
        $saleEnd = $this->firstAttribute($card, Selectors::CARD_SALE_END, 'data-end-date');

        return MovieDto::item(
            url: $url,
            externalId: $sku,
            title: $this->firstText($card, Selectors::CARD_TITLE),
            data: MovieMeta::filter([
                'code' => $sku,
                'cover_url' => $largeCover ?? ($thumbnail === null ? null : $this->absolute($pageUrl, $thumbnail)),
                'performers' => $this->performers($card, $pageUrl),
                'screenshots' => $images['screenshots'],
                'metadata' => MovieMeta::filter([
                    'cover_thumbnail_url' => $thumbnail === null ? null : $this->absolute($pageUrl, $thumbnail),
                    MovieMeta::RATING => $this->ratingFromText($this->firstText($card, Selectors::CARD_REVIEW)),
                    MovieMeta::FAVORITE_COUNT => $this->countFromText($this->firstText($card, Selectors::CARD_WISH)),
                    MovieMeta::PRICE => $minPrice,
                    'original_price' => $originPrice,
                    'discount_label' => $this->firstText($card, Selectors::CARD_DISCOUNT),
                    'sale_ends_at' => $this->saleEnd($saleEnd),
                    'sample_player_url' => $player === null ? null : $this->absolute($pageUrl, $player),
                    'badges' => $this->badges($card->filter(Selectors::CARD_BADGES)),
                ]),
            ]),
        );
    }

    /**
     * The card's actor links; the site only exposes an actor search per name, so there is no id or photo.
     *
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(Crawler $card, string $pageUrl): array
    {
        $performers = [];
        $card->filter(Selectors::CARD_ACTORS)->each(function (Crawler $link) use (&$performers, $pageUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = $link->attr('href');
            if ($name !== null && is_string($href)) {
                $performers[$name] = MovieMeta::performer($name, null, $this->encodedUrl($this->absolute($pageUrl, $href)));
            }
        });

        return array_values($performers);
    }

    /**
     * The "pb_e" jacket is the large cover; every "cap_e_N" still is a sample screenshot, in page order.
     *
     * @return array{cover: ?string, screenshots: list<array{url: string, thumbnail_url: null}>}
     */
    private function sampleImages(Crawler $card, string $pageUrl): array
    {
        $cover = null;
        $screenshots = [];
        $seen = [];
        $card->filter(Selectors::CARD_SAMPLE_IMAGES)->each(function (Crawler $link) use (&$cover, &$screenshots, &$seen, $pageUrl): void {
            $url = $this->absolute($pageUrl, trim((string) $link->attr('href')));
            if (isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;

            if (str_contains($url, '/pb_e_')) {
                $cover ??= $url;

                return;
            }

            $screenshots[] = ['url' => $url, 'thumbnail_url' => null];
        });

        return ['cover' => $cover, 'screenshots' => $screenshots];
    }

    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $current = $this->countFromText($this->firstText($crawler, Selectors::PAGER_CURRENT)) ?? $request->page;
        $nextUrl = null;
        $lastPage = $current;

        $crawler->filter(Selectors::PAGER_LINKS)->each(function (Crawler $link) use (&$nextUrl, &$lastPage, $current, $request): void {
            $href = $link->attr('href');
            $page = is_string($href) ? $this->queryPageNumber($this->absolute($request->url, $href), 'page') : null;
            if ($page === null) {
                return;
            }

            $lastPage = max($lastPage, $page);
            if ($page === $current + 1 && $nextUrl === null) {
                $nextUrl = $this->absolute($request->url, $href);
            }
        });

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: $lastPage,
            nextPage: $nextUrl === null ? null : $current + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }
}
