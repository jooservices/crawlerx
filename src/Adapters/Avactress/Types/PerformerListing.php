<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avactress\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Avactress\Fields;
use JOOservices\CrawlerX\Adapters\Avactress\Selectors;
use JOOservices\CrawlerX\Adapters\Avactress\Site;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The ranking and discovery lists (/popular, /bust, /young, /new): performer cards, paged with `?page=N` where the
 * list is longer than one page.
 */
final class PerformerListing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'performer_listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $fields = new Fields();
        $items = [];

        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$items, $request, $fields): void {
            $url = $fields->anchor($card, $request->url);
            $externalId = $url === null ? null : Site::performerId($url);
            $heading = $this->firstText($card, Selectors::CARD_NAME);
            if ($url === null || $externalId === null || $heading === null || isset($items[$url])) {
                return;
            }

            $texts = $card->filter(Selectors::CARD_TEXTS);
            $stats = $texts->count() > 1 ? $this->normalizeText($texts->eq(1)->text('')) : null;
            $bust = $fields->bust((string) $stats);
            $names = $fields->names($heading);
            $thumbnail = $fields->src($card, Selectors::IMAGE, $request->url);

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $names['name'],
                data: [
                    'name_japanese' => $names['name'],
                    'aliases' => $names['aliases'],
                    'size_raw' => $fields->size($bust['bust_cm'], $bust['cup'], null, null),
                    'metadata' => MovieMeta::filter([
                        'display_name' => $heading,
                        'name_kana' => $texts->count() > 0 ? $this->normalizeText($texts->first()->text('')) : null,
                        'bust_cm' => $bust['bust_cm'],
                        'cup' => $bust['cup'],
                        'age' => $fields->age($stats),
                        'images' => $thumbnail === null ? [] : [$fields->image($thumbnail, $heading)],
                    ]),
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' performer listing did not contain performer cards.');
        }

        $pagination = $fields->pagination($crawler, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination['current'],
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $pagination['current'],
                lastPage: $pagination['last'],
                nextPage: $pagination['next_url'] === null ? null : $pagination['current'] + 1,
                nextUrl: $pagination['next_url'],
                hasNextPage: $pagination['next_url'] !== null,
            ),
        );
    }
}
