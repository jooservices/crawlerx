<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\PerformerDirectory;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
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
        private readonly PerformerDirectoryDefinition $definition,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $fetchUrl = parse_url($request->url, PHP_URL_PATH) === '/' ? $this->definition->listingUrl : $request->url;
        $response = $this->client->get($fetchUrl);
        $html = $this->htmlFromResponse($response->toPsrResponse(), $this->definition->label, 'performer_listing');
        $crawler = new Crawler($html, $fetchUrl);
        $items = [];

        $crawler->filter($this->definition->listingLinkSelector)->each(function (Crawler $link) use (&$items, $fetchUrl): void {
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->absolute($fetchUrl, $href);
            $externalId = $this->definition->externalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $title = $this->title($link) ?? $externalId;
            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: ['profile_image_url' => $this->image($link, $fetchUrl)],
            );
        });

        if ($items === []) {
            throw new CrawlParseException("{$this->definition->label} performer listing did not contain profile links.");
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto($request->page, null, null, null, false),
        );
    }

    private function title(Crawler $link): ?string
    {
        foreach ([$link->attr('title'), $link->filter('img')->first()->attr('alt'), $link->text('')] as $value) {
            if (! is_string($value)) {
                continue;
            }

            $title = $this->normalizeText($value);
            if ($title !== null) {
                return $title;
            }
        }

        return null;
    }

    private function image(Crawler $link, string $baseUrl): ?string
    {
        $image = $link->filter('img');
        if ($image->count() === 0) {
            return null;
        }

        $src = $image->first()->attr('src') ?? $image->first()->attr('data-src');

        return is_string($src) && trim($src) !== '' ? $this->absolute($baseUrl, $src) : null;
    }
}
