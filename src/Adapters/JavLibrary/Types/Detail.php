<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavLibrary\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavLibrary\Selectors;
use JOOservices\CrawlerX\Adapters\JavLibrary\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'JavLibrary', 'detail');
        $crawler = new Crawler($html, $request->url);

        $externalId = $this->externalId($request->url);
        $code = $this->firstText($crawler, Selectors::DETAIL_CODE) ?? CodeNormalizer::canonical(null, $externalId, $request->url);
        $titleRaw = $this->firstText($crawler, Selectors::DETAIL_TITLE);
        $title = $this->titleWithoutCode($titleRaw, $code) ?? $titleRaw ?? $externalId;
        $coverUrl = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'code' => $code,
                'cover_url' => $coverUrl === null ? null : $this->normalizer->absolute($request->url, $coverUrl),
                'date' => $this->date($this->firstText($crawler, Selectors::DETAIL_DATE)),
                'duration' => $this->durationMinutes($this->firstText($crawler, Selectors::DETAIL_LENGTH)),
                'director' => $this->firstText($crawler, Selectors::DETAIL_DIRECTOR),
                'maker' => $this->firstText($crawler, Selectors::DETAIL_MAKER),
                'publisher' => $this->firstText($crawler, Selectors::DETAIL_LABEL),
                'performers' => $this->texts($crawler, Selectors::DETAIL_CAST),
                'tags' => $this->texts($crawler, Selectors::DETAIL_GENRES),
                'screenshots' => $this->screenshots($crawler, $request->url),
            ],
        );

        $this->assertUsableDetail($item, 'JavLibrary');

        return $item;
    }

    /**
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];
        $crawler->filter(Selectors::DETAIL_SCREENSHOTS)->each(function (Crawler $node) use (&$screenshots, $baseUrl): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $thumbnail = null;
            $image = $node->filter('img');
            if ($image->count() > 0) {
                $candidate = $image->first()->attr('src');
                $thumbnail = is_string($candidate) && trim($candidate) !== ''
                    ? $this->normalizer->absolute($baseUrl, $candidate)
                    : null;
            }

            $url = $this->normalizer->absolute($baseUrl, $href);
            $screenshots[$url] = [
                'url' => $url,
                'thumbnail_url' => $thumbnail,
                'position' => count($screenshots) + 1,
            ];
        });

        return array_values($screenshots);
    }

    private function externalId(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            $id = $params['v'] ?? null;
            if (is_string($id) && trim($id) !== '') {
                return trim($id);
            }
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/([a-z0-9]+)\.html$#i', $path, $match) === 1 ? $match[1] : null;
    }

    private function titleWithoutCode(?string $title, ?string $code): ?string
    {
        if ($title === null || $code === null) {
            return $title;
        }

        $trimmed = trim(preg_replace('/^' . preg_quote($code, '/') . '\s+/i', '', $title) ?? $title);

        return $trimmed !== '' ? $trimmed : $title;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = date_create_immutable(trim($value));

        return $date === false ? null : $date->format('Y-m-d');
    }

    private function durationMinutes(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return preg_match('/\b(\d{1,4})(?:\s*min(?:ute)?s?)?\b/i', $value, $match) === 1 ? (int) $match[1] : null;
    }
}
