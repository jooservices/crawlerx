<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Asagei\Types;

use JOOservices\CrawlerX\Adapters\Asagei\Selectors;
use JOOservices\CrawlerX\Adapters\Asagei\Urls;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;
use JOOservices\CrawlerX\Adapters\Concerns\CoercesValues;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;
use JOOservices\CrawlerX\Adapters\Concerns\ResolvesLinkUrls;

final class Listing extends AsageiHtmlType implements TypeInterface
{
    use ResolvesLinkUrls;

    use ParsesUrlParts;

    use CoercesValues;

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $listingLabel = $this->firstText($crawler, Selectors::LISTING_TITLE);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request, $listingLabel): void {
            $item = $this->card($card, $request->url, $listingLabel);
            if ($item !== null) {
                $items[$item->url] ??= $item;
            }
        });

        if ($items === []) {
            throw new CrawlParseException('Asagei listing page did not contain article cards.');
        }

        $currentPage = $this->digitPage($this->firstText($crawler, Selectors::PAGINATION_CURRENT)) ?? $request->page;
        $nextUrl = $this->linkUrl($crawler, Selectors::PAGINATION_NEXT, $request->url);
        $lastUrl = $this->linkUrl($crawler, Selectors::PAGINATION_LAST, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $currentPage,
            entityType: 'gallery',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $currentPage,
                lastPage: $lastUrl === null ? null : $this->queryNumber($lastUrl, 'page'),
                nextPage: $nextUrl === null ? null : $this->queryNumber($nextUrl, 'page'),
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function card(Crawler $card, string $baseUrl, ?string $listingLabel): ?CrawlItemResultDto
    {
        $href = $this->firstAttribute($card, Selectors::CARD_TITLE, 'href')
            ?? $this->firstAttribute($card, '.content-thumb-pc a', 'href');
        $url = $href === null ? null : Urls::article($this->absolute($baseUrl, $href));
        $externalId = $url === null ? null : Urls::externalId($url);
        if ($url === null || $externalId === null) {
            return null;
        }

        $title = $this->firstText($card, Selectors::CARD_TITLE);
        $posted = $this->postedAt($card, Selectors::CARD_DATE, Selectors::ARTICLE_DATETIME);
        $categories = $this->labelledLinks($card, 'カテゴリー', $baseUrl);
        $tags = $this->labelledLinks($card, 'タグ', $baseUrl);
        $categoryHead = $this->firstText($card, Selectors::CARD_CATEGORY_HEAD);
        $categoryNames = $this->linkNames($categories);
        if ($categoryHead !== null && ! in_array($categoryHead, $categoryNames, true)) {
            array_unshift($categoryNames, $categoryHead);
        }

        $thumbNode = $card->filter(Selectors::CARD_THUMB);
        $thumb = $thumbNode->count() === 0 ? null : $this->imageSource($thumbNode->first());
        $sourceFields = MovieMeta::filter([
            'Posted on' => $posted['raw'],
            'カテゴリー' => $categoryNames === [] ? null : implode(', ', $categoryNames),
            'タグ' => $tags === [] ? null : implode(', ', $this->linkNames($tags)),
        ]);

        return GalleryDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [
                'date' => $posted['date'],
                'categories' => $categoryNames,
                'tags' => $this->linkNames($tags),
                'metadata' => MovieMeta::filter([
                    'published_at' => $posted['published_at'],
                    'excerpt' => $this->firstText($card, Selectors::CARD_EXCERPT),
                    'listing_label' => $listingLabel,
                    'category_urls' => $this->linkUrls($categories),
                    'tag_urls' => $this->linkUrls($tags),
                    'cover' => $this->coverFromUrl($thumb, $baseUrl),
                    'source_fields' => $sourceFields,
                ]),
            ],
        )->withNextCrawlType('gallery');
    }
}
