<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Selectors;
use JOOservices\CrawlerX\Adapters\MinnanoAv\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private readonly UrlNormalizer $normalizer;

    public function __construct(CrawlHttpClient $client)
    {
        parent::__construct($client);
        $this->normalizer = new UrlNormalizer();
    }

    protected function siteLabel(): string
    {
        return 'minnanoav';
    }

    protected function contextLabel(): string
    {
        return 'av_detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $externalId = $this->externalId($request->url);
        $title = $this->firstText($crawler, Selectors::AV_DETAIL_HEADING)
            ?? $this->firstText($crawler, Selectors::AV_DETAIL_TITLE)
            ?? $externalId
            ?? '';
        $fields = $this->profileFields($crawler);
        $productCode = $this->firstAttribute($crawler, Selectors::AV_PRODUCT_CODE, 'data-code');
        $code = $this->normalizeText($productCode ?? '') ?? CodeNormalizer::canonical($title, $externalId, $request->url);
        [$maker, $label] = $this->makerAndLabel($fields);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: array_filter([
                'code' => $code,
                'title' => $title !== '' ? $title : null,
                'cover_url' => $this->coverUrl($crawler, $request->url),
                'description' => $this->fieldText($fields, ['紹介文', '作品紹介', 'あらすじ']),
                'date' => $this->isoDate($this->fieldText($fields, ['発売日'])),
                'duration' => $this->durationMinutes($this->fieldText($fields, ['収録時間', '再生時間'])),
                'performers' => $this->performers($crawler, $request->url),
                'tags' => $this->fieldLinks($fields, ['ジャンル', 'タグ']),
                'sample_video_url' => $this->firstAttribute($crawler, Selectors::AV_SAMPLE_VIDEO, 'src'),
                'metadata' => array_filter([
                    'maker' => $maker,
                    'label' => $label,
                    'series' => $this->fieldText($fields, ['シリーズ']),
                    'director' => $this->fieldText($fields, ['監督']),
                    'release_date_raw' => $this->fieldText($fields, ['発売日']),
                    'maker_url' => $this->cellLink($fields['メーカー/レーベル'] ?? $fields['メーカー'] ?? null, 'a[href*="maker="]', $request->url),
                    'label_url' => $this->cellLink($fields['メーカー/レーベル'] ?? $fields['メーカー'] ?? null, 'a[href*="label="]', $request->url),
                    'fanza_url' => $this->fanzaUrl($crawler),
                    'fanza_content_id' => $this->fanzaContentId($crawler),
                    'minnano_code' => $externalId !== null ? strtoupper(preg_replace('/^av/i', 'AV-', $externalId) ?? '') : null,
                ], static fn(mixed $value): bool => $value !== null && $value !== ''),
            ], static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []),
        );

        $this->assertUsableFilmographyDetail($item);

        return $item;
    }

    /** @return array<string, Crawler> label => value cell */
    private function profileFields(Crawler $crawler): array
    {
        $fields = [];
        $crawler->filter(Selectors::AV_INFO_ROWS)->each(function (Crawler $row) use (&$fields): void {
            $cells = $row->filter('td');
            if ($cells->count() < 2) {
                return;
            }

            $label = $this->normalizeText($cells->eq(0)->text(''));
            if ($label !== null && ! isset($fields[$label])) {
                $fields[$label] = $cells->eq(1);
            }
        });

        return $fields;
    }

    /**
     * @param  array<string, Crawler>  $fields
     * @param  list<string>  $labels
     */
    private function fieldText(array $fields, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (isset($fields[$label])) {
                $text = $this->normalizeText($fields[$label]->text(''));
                if ($text !== null) {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, Crawler>  $fields
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function fieldLinks(array $fields, array $labels): array
    {
        foreach ($labels as $label) {
            if (isset($fields[$label])) {
                return $this->texts($fields[$label], 'a');
            }
        }

        return [];
    }

    /**
     * @param  array<string, Crawler>  $fields
     * @return array{0: ?string, 1: ?string}
     */
    private function makerAndLabel(array $fields): array
    {
        $cell = $fields['メーカー/レーベル'] ?? $fields['メーカー'] ?? null;
        if ($cell === null) {
            return [null, null];
        }

        return [
            $this->firstText($cell, 'a[href*="maker="]'),
            $this->firstText($cell, 'a[href*="label="]'),
        ];
    }

    private function cellLink(?Crawler $cell, string $selector, string $baseUrl): ?string
    {
        $href = $cell === null || $cell->filter($selector)->count() === 0 ? null : $cell->filter($selector)->first()->attr('href');

        return is_string($href) && $href !== '' ? $this->normalizer->absolute($baseUrl, $href) : null;
    }

    /** The shop link is an affiliate redirect; `lurl` holds the real product page. */
    private function fanzaUrl(Crawler $crawler): ?string
    {
        $link = $crawler->filter('a.dmm-link, a.jacket-link[href*="lurl="]');
        $query = $link->count() > 0 ? parse_url(html_entity_decode((string) $link->first()->attr('href')), PHP_URL_QUERY) : null;
        parse_str(is_string($query) ? $query : '', $params);

        return is_string($params['lurl'] ?? null) && $params['lurl'] !== '' ? $params['lurl'] : null;
    }

    private function fanzaContentId(Crawler $crawler): ?string
    {
        $url = $this->fanzaUrl($crawler);

        return $url !== null && preg_match('/[?&]id=([^&]+)/', $url, $match) === 1 ? $match[1] : null;
    }

    private function coverUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, Selectors::AV_COVER, 'src')
            ?? $this->firstAttribute($crawler, 'meta[property="og:image"]', 'content');

        return $src === null ? null : $this->normalizer->absolute($baseUrl, $src);
    }

    /** @return list<array{external_id: string, name: string, url: string}> */
    private function performers(Crawler $crawler, string $baseUrl): array
    {
        $performers = [];
        $crawler->filter(Selectors::AV_PERFORMER_LINKS)->each(function (Crawler $node) use (&$performers, $baseUrl): void {
            $href = $node->attr('href');
            if (! is_string($href) || preg_match('#actress(\d+)\.html#i', $href, $match) !== 1) {
                return;
            }

            $name = $this->normalizeText($node->filter('.actress-name')->count() > 0 ? $node->filter('.actress-name')->text('') : $node->text(''));
            if ($name === null || isset($performers[$match[1]])) {
                return;
            }

            $performers[$match[1]] = [
                'external_id' => $match[1],
                'name' => $name,
                'url' => $this->normalizer->canonicalActressUrl($this->normalizer->absolute($baseUrl, $href)),
            ];
        });

        return array_values($performers);
    }

    private function isoDate(?string $text): ?string
    {
        if ($text !== null && preg_match('/(\d{4})\D+(\d{1,2})\D+(\d{1,2})/u', $text, $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3]);
        }

        return null;
    }

    private function durationMinutes(?string $text): ?int
    {
        if ($text !== null && preg_match('/(\d+)\s*分/u', $text, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function assertUsableFilmographyDetail(CrawlItemResultDto $item): void
    {
        $movie = $item->meta['movie'] ?? [];
        $title = is_array($movie) ? ($movie['title'] ?? null) : null;
        $hasTitle = is_string($title) && trim($title) !== '';
        $code = is_array($movie) ? ($movie['code'] ?? null) : null;
        $hasCode = is_string($code) && trim($code) !== '';

        if (! $hasTitle || ! $hasCode) {
            throw new \RuntimeException('minnanoav detail page did not contain expected movie fields.');
        }
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/(av\d+)\.html$#i', $path, $match) === 1) {
            return strtolower($match[1]);
        }

        return null;
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        return $this->normalizeText($node->first()->text(''));
    }
}
