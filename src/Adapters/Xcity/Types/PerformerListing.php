<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;
use JOOservices\CrawlerX\Adapters\Concerns\CoercesValues;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;

final class PerformerListing extends AbstractType implements TypeInterface
{
    use CoercesValues;
    use ParsesHtml;
    use ParsesUrlParts;

    private const CARD_IMAGES = '#avidol a[href*="detail/"] img[src]';

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'xcity', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];
        $images = $this->cardImages($crawler, $request->url);

        $crawler->filter('#avidol a[href*="/idol/detail/"], #avidol a[href*="/idol/?id="], #avidol a[href*="detail/"]')
            ->each(function (Crawler $node) use (&$items, $images, $request): void {
                $href = $node->attr('href');
                $name = $this->listingName($node);
                if (! is_string($href) || $href === '' || $name === '') {
                    return;
                }

                $url = UriResolver::resolve($href, $request->url);
                $externalId = $this->pathMatchOrQueryParam($url, '#/idol/detail/([^/]+)/?#i', 'id');
                if ($externalId === null) {
                    return;
                }

                $items[$url] = PerformerDto::item(
                    url: $url,
                    externalId: $externalId,
                    title: $name,
                    data: isset($images[$url]) ? ['profile_image_url' => $images[$url]] : [],
                )->withNextCrawlType('performer_detail');
            });

        $nextUrl = $this->nextPageUrl($crawler, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: $nextUrl !== null ? $this->queryPageNumber($nextUrl, 'page') : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    /**
     * Portrait of every card, keyed by detail URL: the card has an image link and a name link to the same page.
     *
     * @return array<string, string>
     */
    private function cardImages(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter(self::CARD_IMAGES)->each(function (Crawler $image) use (&$images, $baseUrl): void {
            $href = $image->closest('a')?->attr('href');
            $src = $image->attr('src');
            if (is_string($href) && $href !== '' && is_string($src) && trim($src) !== '') {
                $images[UriResolver::resolve($href, $baseUrl)] ??= UriResolver::resolve($src, $baseUrl);
            }
        });

        return $images;
    }

    private function listingName(Crawler $node): string
    {
        $title = $node->attr('title');
        if (is_string($title) && trim($title) !== '') {
            return $this->cleanTextWithoutTrailingPhrase($title, 'All Titles Information');
        }

        $imgAlt = $node->filter('img')->count() > 0 ? $node->filter('img')->first()->attr('alt') : null;
        if (is_string($imgAlt) && trim($imgAlt) !== '') {
            return $this->cleanTextWithoutTrailingPhrase($imgAlt, 'All Titles Information');
        }

        return $this->cleanTextWithoutTrailingPhrase($node->text(''), 'All Titles Information');
    }

    private function nextPageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $nodes = $crawler->filter('.pageScrl li.next a[href*="page="], .pageScrl a[rel="next"][href*="page="]');
        if ($nodes->count() === 0) {
            return null;
        }

        $href = $nodes->first()->attr('href');
        if (! is_string($href) || $href === '') {
            return null;
        }

        return UriResolver::resolve($href, $baseUrl);
    }
}
