<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OneFourOneJav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;
use JOOservices\CrawlerX\Adapters\OneFourOneJav\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Adapters\Shared\OnejavTheme\TorrentCardFields;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;
    use ParsesUrlParts;

    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), '141jav', 'detail');

        return $this->parse($request->url, $html);
    }

    private function parse(string $url, string $html): CrawlItemResultDto
    {
        $crawler = new Crawler($html, $url);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE);
        $externalId = $this->lastPathSegment($url, allowEmptySegment: true);

        $fields = TorrentCardFields::read($crawler, $url, fn(string $base, string $href): string => $this->absoluteFromOrigin($base, $href));
        $item = MovieDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [...$fields, 'code' => CodeNormalizer::canonical($title, $externalId, $url)],
        );

        $this->assertUsableDetail($item, '141jav');

        return $item;
    }
}
