<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OneFourOneJav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\OneFourOneJav\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

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
        $externalId = $this->externalId($url);

        $item = MovieDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [
                'cover_url' => $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src'),
                'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION),
                'code' => CodeNormalizer::canonical($title, $externalId, $url),
                'tags' => $this->texts($crawler, Selectors::DETAIL_TAGS),
                'date' => $this->date($crawler),
                'performers' => $this->texts($crawler, Selectors::DETAIL_PERFORMERS),
                'download_url' => $this->downloadUrl($crawler, $url),
            ],
        );

        $this->assertUsableDetail($item, '141jav');

        return $item;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? trim(basename($path), '/') : null;
    }

    private function date(Crawler $crawler): ?string
    {
        $text = $this->firstText($crawler, Selectors::DETAIL_DATE);
        if ($text === null) {
            return null;
        }

        $date = date_create_immutable(str_replace('May.', 'May', $text));

        return $date === false ? null : $date->format('Y-m-d');
    }

    private function downloadUrl(Crawler $crawler, string $sourceUrl): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_DOWNLOAD)->count() === 0) {
            return null;
        }

        foreach ($crawler->filter(Selectors::DETAIL_DOWNLOAD) as $node) {
            $href = (new Crawler($node))->attr('href');
            if (is_string($href) && trim($href) !== '') {
                return $this->absoluteUrl($sourceUrl, $href);
            }
        }

        return null;
    }

    private function absoluteUrl(string $sourceUrl, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($sourceUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return rtrim($origin, '/') . '/' . ltrim($href, '/');
    }
}
