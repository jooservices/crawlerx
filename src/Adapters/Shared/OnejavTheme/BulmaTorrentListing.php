<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnejavTheme;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesBulmaPagination;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

abstract class BulmaTorrentListing extends AbstractHtmlType implements TypeInterface
{
    use ParsesBulmaPagination;

    /**
     * @param  callable(string, string): string  $normalize
     * @param  (callable(string, int): ?string)|null  $nextUrlFallback
     */
    public function __construct(
        CrawlHttpClient $client,
        private readonly BulmaTorrentListingProfile $profile,
        private $normalize,
        private $nextUrlFallback = null,
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return $this->profile->siteLabel;
    }

    protected function contextLabel(): string
    {
        return $this->profile->contextLabel;
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = $this->parseItems($crawler, $request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->parseBulmaPagination(
                $crawler,
                $request->url,
                $this->normalize,
                $this->nextUrlFallback,
            ),
        );
    }

    /**
     * @return array<string, CrawlItemResultDto>
     */
    protected function parseItems(Crawler $crawler, CrawlRequestDto $request): array
    {
        $items = [];
        $normalize = $this->normalize;

        $crawler->filter($this->profile->listingLinksSelector)->each(function (Crawler $node) use (&$items, $request, $normalize): void {
            $href = $node->attr('href');
            if (! is_string($href)) {
                return;
            }

            $absolute = $normalize($request->url, $href);
            if (preg_match($this->profile->itemUrlPattern, $absolute) !== 1) {
                return;
            }

            $path = parse_url($absolute, PHP_URL_PATH);
            $externalId = is_string($path) ? trim(basename($path), '/') : '';

            $items[$absolute] = MovieDto::item(
                url: $absolute,
                externalId: $externalId,
                title: trim($node->text('')) !== '' ? trim($node->text('')) : null,
            );
        });

        return $items;
    }
}
