<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tokuma\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Tokuma\TokumaSite;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\Entity\ScreenshotDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url)->toPsrResponse();
        $dom = new Crawler($this->htmlFromResponse($response, 'Tokuma Shoten', 'book detail'));
        $body = $dom->filter('#main .detail')->first();
        $titleNode = $dom->filter('#main .titleWrap h1')->first();
        $title = $titleNode->count() > 0 ? self::normalize($titleNode->text('')) : null;
        $id = TokumaSite::externalId($request->url);
        if ($body->count() === 0 || $title === null || $id === null) {
            throw new CrawlParseException('Tokuma Shoten detail did not contain expected book fields.');
        }

        $rows = [];
        $genres = [];
        $series = [];
        $authors = [];
        foreach ($body->filter('table tr') as $element) {
            $row = new Crawler($element);
            $label = self::normalize($row->filter('th')->text(''));
            $value = $row->filter('td')->first();
            if ($label === '' || $value->count() === 0) {
                continue;
            }
            $rows[$label] = self::textContent($value);
            $links = self::links($value, $request->url);
            if ($label === 'ジャンル') {
                $genres = $links;
            } elseif ($label === 'シリーズ') {
                $series = $links;
            } elseif ($label === '著者') {
                $authors = self::authors($value, $request->url);
            }
        }

        $cover = $body->filter('.left .cover img[src]')->first();
        $coverUrl = $cover->count() > 0 ? TokumaSite::absolute($request->url, $cover->attr('src') ?? '') : null;
        if (TokumaSite::isPlaceholderImage($coverUrl)) {
            $coverUrl = null;
        }
        $summary = self::text($body, '.right > p');
        $longDescription = self::text($dom, '#bookSample');
        $descriptionParts = array_filter([$summary, $longDescription], static fn(?string $value): bool => $value !== null);
        $description = $descriptionParts === [] ? null : implode("\n\n", $descriptionParts);
        $publicationDate = self::isoDate($rows['出版年月日'] ?? null);
        $isbn = $rows['ISBN'] ?? null;
        $format = $rows['判型・ページ数'] ?? null;
        $pages = $format !== null && preg_match('/(\d+)\s*ページ/u', $format, $match) === 1 ? (int) $match[1] : null;
        $performers = [];
        $screenshots = $coverUrl === null ? [] : [new ScreenshotDto($coverUrl, metadata: ['role' => 'cover'])];
        $item = MovieDto::item(TokumaSite::detailUrl($id), $id, $title, [
            'code' => $isbn,
            'description' => $description,
            'date' => $publicationDate,
            'cover_url' => $coverUrl,
            'performers' => $performers,
            'tags' => array_map(static fn(array $genre): string => $genre['name'], $genres),
            'screenshots' => $screenshots,
            'metadata' => MovieMeta::filter([
                'maker' => 'Tokuma Shoten',
                'isbn' => $isbn,
                'authors' => $authors,
                'genres' => $genres,
                'series' => $series === [] ? null : implode(', ', array_column($series, 'name')),
                'series_links' => $series,
                'format' => $format,
                'pages' => $pages,
                'price' => TokumaSite::price($rows['定価'] ?? null),
                'image_count' => $coverUrl === null ? 0 : 1,
                'source_fields' => $rows,
            ]),
        ]);
        $this->assertUsableMovieDetail($item, 'Tokuma Shoten');

        return $item;
    }

    private static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function textContent(Crawler $node): string
    {
        $html = preg_replace('/<br\s*\/?\s*>/i', ' ', $node->html()) ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::normalize($text);
    }

    private static function text(Crawler $dom, string $selector): ?string
    {
        $node = $dom->filter($selector)->first();
        if ($node->count() === 0) {
            return null;
        }
        $value = self::normalize($node->text(''));

        return $value === '' ? null : $value;
    }

    /** @return list<array{name: string, url: string}> */
    private static function links(Crawler $dom, string $baseUrl): array
    {
        $links = [];
        foreach ($dom->filter('a[href]') as $element) {
            $node = new Crawler($element);
            $name = self::normalize($node->text(''));
            if ($name !== '') {
                $links[] = ['name' => $name, 'url' => TokumaSite::absolute($baseUrl, $node->attr('href') ?? '')];
            }
        }

        return $links;
    }

    /** @return list<array{name: string, url: string, role: string}> */
    private static function authors(Crawler $dom, string $baseUrl): array
    {
        $authors = [];
        foreach ($dom->filter('a[href]') as $element) {
            $node = new Crawler($element);
            $name = self::normalize($node->text(''));
            if ($name !== '') {
                $role = self::normalize($node->getNode(0)->nextSibling->textContent ?? '');
                $authors[] = ['name' => $name, 'url' => TokumaSite::absolute($baseUrl, $node->attr('href') ?? ''), 'role' => $role];
            }
        }

        return $authors;
    }

    private static function isoDate(?string $date): ?string
    {
        if ($date === null || preg_match('#^(\d{4})/(\d{2})/(\d{2})$#', $date, $match) !== 1) {
            return null;
        }

        return $match[1] . '-' . $match[2] . '-' . $match[3];
    }
}
