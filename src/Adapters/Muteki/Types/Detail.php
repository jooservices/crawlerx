<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muteki\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Muteki\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $dom = new Crawler($this->htmlFromResponse($response->toPsrResponse(), Site::MAKER, 'detail'));
        $body = $dom->filter('.area-content .sect-detail');
        if ($body->count() === 0) {
            throw new CrawlParseException(Site::MAKER . ' detail page did not contain expected work details.');
        }

        $id = strtoupper(trim(basename((string) parse_url($request->url, PHP_URL_PATH)), '/'));
        $rows = $this->rows($body);
        $codes = $this->formatValues($body, '.bx-detail-work > div');
        $durations = $this->formatValues($body, '.bx-detail-time > div');
        $formats = $this->formats($codes, $durations);
        $dateRaw = $rows['発売日']['value'] ?? null;
        $performers = $this->performers($body);
        $coverThumbnail = $this->absoluteAttribute($body, '.area-package > img', 'src');
        $cover = $this->absoluteAttribute($body, '.bx-package-expansion[href]', 'href') ?? $coverThumbnail;
        $screenshots = $cover === null ? [] : [[
            'url' => $cover,
            'thumbnail_url' => $coverThumbnail === $cover ? null : $coverThumbnail,
            'position' => 0,
        ]];
        $item = MovieDto::item(Site::BASE_URL . '/works/detail/' . strtolower($id) . '/', $id, $this->firstText($body, '.area-detail-title h1'), [
            'code' => $id,
            'description' => $this->firstText($body, '.bx-detail > p.tx-normal-14'),
            'date' => $this->date($dateRaw),
            'duration' => $formats[0]['duration'] ?? null,
            'cover_url' => $cover,
            'screenshots' => $screenshots,
            'performers' => $performers,
            'metadata' => [
                'maker' => Site::MAKER,
                'formats' => $formats,
                'cover_thumbnail_url' => $coverThumbnail,
                'release_links' => $this->releaseLinks($body),
                'purchase_links' => $this->links($body, '.area-buy a[href]'),
                'prices' => $this->texts($body, '.area-buy .tx-price'),
                'sample_video_url' => $this->absoluteAttribute($body, '.area-movie video[src]', 'src'),
                'source_fields' => $this->sourceFields($rows),
            ],
        ]);
        $this->assertUsableMovieDetail($item, Site::MAKER);

        return $item;
    }

    /** @return array<string, array{value: string, node: Crawler}> */
    private function rows(Crawler $body): array
    {
        $rows = [];
        foreach ($body->filter('.lst-actress dl.tx-detail, .bx-detail > dl.tx-detail') as $element) {
            $row = new Crawler($element);
            $label = $this->firstText($row, 'dt');
            $value = $this->firstText($row, 'dd');
            if ($label !== null && $value !== null) {
                $rows[$label] = ['value' => $value, 'node' => $row];
            }
        }

        return $rows;
    }

    /** @param array<string, array{value: string, node: Crawler}> $rows
     * @return array<string, string>
     */
    private function sourceFields(array $rows): array
    {
        $fields = [];
        foreach ($rows as $label => $row) {
            $fields[$label] = $label . ' ' . ($this->firstText($row['node'], 'dd') ?? $row['value']);
        }

        return $fields;
    }

    /**
     * @param list<array{format: string, value: string}> $codes
     * @param list<array{format: string, value: string}> $durations
     * @return list<array{format: string, code: ?string, duration: ?int}>
     */
    private function formats(array $codes, array $durations): array
    {
        $formats = [];
        foreach ($codes as $code) {
            $duration = null;
            foreach ($durations as $candidate) {
                if ($candidate['format'] === $code['format']) {
                    $duration = $this->minutes($candidate['value']);
                    break;
                }
            }
            $formats[] = ['format' => $code['format'], 'code' => $code['value'], 'duration' => $duration];
        }

        return $formats;
    }

    /** @return list<array{format: string, value: string}> */
    private function formatValues(Crawler $node, string $selector): array
    {
        $values = [];
        foreach ($node->filter($selector) as $element) {
            $entry = new Crawler($element);
            $format = $this->firstText($entry, 'span');
            $text = $this->normalizeText($entry->text('')) ?? '';
            $value = $format === null ? $text : trim(preg_replace('/^' . preg_quote($format, '/') . '/u', '', $text) ?? $text);
            if ($value !== '') {
                $values[] = ['format' => $format ?? 'default', 'value' => $value];
            }
        }

        return $values;
    }

    /** @return list<array{name: string, name_japanese: string, url: string, external_id: string}> */
    private function performers(Crawler $body): array
    {
        $performers = [];
        foreach ($body->filter('.lst-actress-item dd a[href]') as $element) {
            $link = new Crawler($element);
            $url = $this->absolute(Site::BASE_URL, (string) $link->attr('href'));
            $name = $this->normalizeText($link->text('')) ?? '';
            if ($name !== '') {
                $performers[$url] = [
                    'name' => $name,
                    'name_japanese' => $name,
                    'url' => $url,
                    'external_id' => trim(basename((string) parse_url($url, PHP_URL_PATH)), '/'),
                ];
            }
        }

        return array_values($performers);
    }

    /** @return list<array{name: string, url: string, external_id: string}> */
    private function releaseLinks(Crawler $body): array
    {
        return array_map(function (array $link): array {
            return [...$link, 'external_id' => trim(basename((string) parse_url($link['url'], PHP_URL_PATH)), '/')];
        }, $this->links($body, '.bx-detail > dl.tx-detail a[href]'));
    }

    /** @return list<array{name: string, url: string}> */
    private function links(Crawler $body, string $selector): array
    {
        return $body->filter($selector)->each(function (Crawler $link): array {
            return [
                'name' => $this->normalizeText($link->text('')) ?? '',
                'url' => $this->absolute(Site::BASE_URL, (string) $link->attr('href')),
            ];
        });
    }

    private function absoluteAttribute(Crawler $body, string $selector, string $attribute): ?string
    {
        $value = $this->firstAttribute($body, $selector, $attribute);

        return $value === null ? null : $this->absolute(Site::BASE_URL, $value);
    }

    private function date(?string $value): ?string
    {
        if ($value === null || preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})/u', $value, $match) !== 1) {
            return null;
        }

        return checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            ? sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3])
            : null;
    }

    private function minutes(string $value): ?int
    {
        return preg_match('/(\d+)分/u', $value, $match) === 1 ? (int) $match[1] : null;
    }
}
