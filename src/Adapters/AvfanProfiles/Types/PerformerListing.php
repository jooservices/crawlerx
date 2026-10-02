<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvfanProfiles\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\AvfanProfiles\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerListing extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'avfan_profiles', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(Selectors::PERFORMER_CARDS)->each(function (Crawler $node) use (&$items, $request): void {
            $href = $node->attr('href');
            $title = $this->normalizeText($node->text(''));
            if (! is_string($href) || $href === '' || $title === null) {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $externalId = $this->externalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
            );
        });

        if ($items === []) {
            throw new CrawlParseException('AvfanProfiles listing page did not contain performer cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: 1,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: 1,
                lastPage: null,
                nextPage: null,
                nextUrl: null,
                hasNextPage: false,
            ),
        );
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/actress/([^/]+)\.html$#i', $path, $match) === 1) {
            $slug = $match[1];

            return $slug;
        }

        return null;
    }
}
