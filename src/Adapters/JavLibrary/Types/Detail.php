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
use JOOservices\CrawlerX\Support\MovieMeta;
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

        $maker = $this->linked($crawler, Selectors::DETAIL_MAKER, $request->url);
        $label = $this->linked($crawler, Selectors::DETAIL_LABEL, $request->url);
        $director = $this->linked($crawler, Selectors::DETAIL_DIRECTOR, $request->url);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: MovieMeta::filter([
                'code' => $code,
                'cover_url' => $coverUrl === null ? null : $this->normalizer->absolute($request->url, $coverUrl),
                'date' => $this->date($this->firstText($crawler, Selectors::DETAIL_DATE)),
                'duration' => $this->durationMinutes($this->firstText($crawler, Selectors::DETAIL_LENGTH)),
                'performers' => $this->performers($crawler, $request->url),
                'tags' => $this->texts($crawler, Selectors::DETAIL_GENRES),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::DIRECTOR => $director['name'] ?? null,
                    MovieMeta::MAKER => $maker['name'] ?? null,
                    MovieMeta::LABEL => $label['name'] ?? null,
                    'director_url' => $director['url'] ?? null,
                    'maker_url' => $maker['url'] ?? null,
                    'label_url' => $label['url'] ?? null,
                    MovieMeta::RATING => $this->rating($this->firstText($crawler, '#video_review .score')),
                    'want_count' => $this->memberCount($crawler, 'userswanted.php'),
                    'watched_count' => $this->memberCount($crawler, 'userswatched.php'),
                    'owned_count' => $this->memberCount($crawler, 'usersowned.php'),
                ]),
            ]),
        );

        $this->assertUsableDetail($item, 'JavLibrary');

        return $item;
    }

    /** @return array{name: string, url: ?string}|null */
    private function linked(Crawler $crawler, string $selector, string $baseUrl): ?array
    {
        $link = $crawler->filter($selector);
        if ($link->count() === 0) {
            return null;
        }

        $name = $this->normalizeText($link->first()->text(''));
        $href = $link->first()->attr('href');

        return $name === null ? null : ['name' => $name, 'url' => is_string($href) && $href !== '' ? $this->normalizer->absolute($baseUrl, $href) : null];
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    private function performers(Crawler $crawler, string $baseUrl): array
    {
        $performers = [];
        $crawler->filter(Selectors::DETAIL_CAST)->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = (string) $link->attr('href');
            if ($name === null) {
                return;
            }

            $id = preg_match('/[?&]s=([a-z0-9]+)/i', $href, $match) === 1 ? $match[1] : null;
            $performers[$id ?? $name] ??= MovieMeta::performer($name, $id, $href === '' ? null : $this->normalizer->absolute($baseUrl, $href));
        });

        return array_values($performers);
    }

    /**
     * "(9.00)" out of 10; an unrated video has an empty score.
     *
     * @return array{score: float, scale: ?float, votes: ?int}|null
     */
    private function rating(?string $text): ?array
    {
        return $text !== null && preg_match('/(\d+(?:\.\d+)?)/', $text, $match) === 1 ? MovieMeta::rating((float) $match[1], 10.0) : null;
    }

    /** "(<a href="userswanted.php?v=..">72</a> members want it)" */
    private function memberCount(Crawler $crawler, string $page): ?int
    {
        $link = $crawler->filter('#video_favorite_edit a[href*="' . $page . '"]');
        $text = $link->count() > 0 ? $this->normalizeText($link->first()->text('')) : null;

        return $text !== null && preg_match('/^\d[\d,]*$/', $text) === 1 ? (int) str_replace(',', '', $text) : null;
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
