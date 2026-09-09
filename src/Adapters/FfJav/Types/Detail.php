<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\FfJav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\FfJav\Selectors;
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
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'ffjav', 'detail');

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
                'description' => $this->description($html),
                'code' => CodeNormalizer::canonical($title, $externalId, $url),
                'tags' => $this->texts($crawler, Selectors::DETAIL_TAGS),
                'date' => $this->date($crawler),
                'performers' => $this->performers($html),
                'download_url' => $this->downloadUrl($crawler),
            ],
        );

        $this->assertUsableDetail($item, 'ffjav');

        return $item;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? trim(basename($path), '/') : null;
    }

    private function description(string $html): ?string
    {
        foreach (
            [
                '/<div class="tags">.*?<\/div>\s*(.*?)<span\s+id=/is',
                '/<div class="tags">.*?<\/div>\s*(.*?)<table>/is',
            ] as $pattern
        ) {
            if (preg_match($pattern, $html, $match) === 1) {
                return $this->normalizeText(strip_tags($match[1]));
            }
        }

        return null;
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

    /**
     * @return list<string>
     */
    private function performers(string $html): array
    {
        if (preg_match('/出演者:\s*([^<]+)/u', $html, $match) !== 1) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn(string $name): string => $this->normalizeText($name) ?? '', explode(',', html_entity_decode($match[1]))),
            fn(string $name): bool => $name !== '',
        )));
    }

    private function downloadUrl(Crawler $crawler): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_DOWNLOAD)->count() === 0) {
            return null;
        }

        $href = $crawler->filter(Selectors::DETAIL_DOWNLOAD)->first()->attr('href');

        return is_string($href) && trim($href) !== '' ? trim($href) : null;
    }
}
