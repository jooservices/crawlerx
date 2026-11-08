<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Dahlia\Fields;
use JOOservices\CrawlerX\Adapters\Dahlia\Selectors;
use JOOservices\CrawlerX\Adapters\Dahlia\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The /actress/ roster: a single page without pagination.
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

        $crawler->filter(Selectors::PERFORMER_CARD)->each(function (Crawler $card) use (&$items, $request, $fields): void {
            $url = $fields->link($card, Selectors::PERFORMER_CARD_LINK, $request->url);
            $externalId = $url === null ? null : Site::performerId($url);
            $romaji = $this->firstText($card, Selectors::PERFORMER_CARD_ROMAJI);
            $name = $this->firstText($card, Selectors::PERFORMER_CARD_NAME);
            $name = $name !== null && $romaji !== null ? $this->normalizeText(substr($name, 0, -strlen($romaji))) : $name;
            if ($url === null || $externalId === null || $name === null || isset($items[$url])) {
                return;
            }

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $name,
                data: [
                    'name_japanese' => $name,
                    'profile_image_url' => $fields->image($card, Selectors::PERFORMER_CARD_PHOTO, $request->url),
                    'aliases' => $romaji === null ? [] : [$romaji],
                    'metadata' => array_filter(['name_romaji' => $romaji], static fn(?string $value): bool => $value !== null),
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException(Site::NAME . ' performer listing did not contain performer cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: 1,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(currentPage: 1, lastPage: 1, nextPage: null, nextUrl: null, hasNextPage: false),
        );
    }
}
