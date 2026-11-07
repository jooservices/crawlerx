<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Indies\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Indies\Fields;
use JOOservices\CrawlerX\Adapters\Indies\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/** Indies actress profile: name, reading, social links, body data (rows left empty by the site are dropped) and works. */
final class PerformerDetail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const SOCIAL_ROW = 'ソーシャル';

    protected function siteLabel(): string
    {
        return Site::LABEL;
    }

    protected function contextLabel(): string
    {
        return 'performer_detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $this->rows($crawler);
        // The social row holds icons, not text; its links are read separately.
        $profile = array_filter(
            array_map(static fn(Crawler $cell): ?string => Fields::meaningful(Fields::visibleText($cell)), array_diff_key($rows, [self::SOCIAL_ROW => true])),
            static fn(?string $value): bool => $value !== null,
        );
        $externalId = Fields::slug($request->url, 'actress');
        $image = $this->firstAttribute($crawler, 'a.img_popup', 'href');

        $item = PerformerDto::item($request->url, $externalId, $profile['女優名'] ?? $externalId, MovieMeta::filter([
            'profile_image_url' => $image === null ? null : $this->absolute($request->url, $image),
            'birth_date_raw' => MovieMeta::isoDate($profile['生年月日'] ?? null),
            'height_raw' => $profile['身長'] ?? null,
            'size_raw' => $profile['スリーサイズ'] ?? null,
            'raw_profile' => $profile,
            'metadata' => MovieMeta::filter([
                'reading' => $profile['ヨミガナ'] ?? null,
                'social_links' => $this->socialLinks($rows[self::SOCIAL_ROW] ?? null),
                'works' => $this->works($crawler, $request->url),
            ]),
        ]));

        $this->assertUsablePerformerDetail($item, Site::LABEL);

        return $item;
    }

    /** @return array<string, Crawler> row label => value cell */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('ul[itemtype$="schema.org/Person"] > li.d-flex')->each(function (Crawler $row) use (&$rows): void {
            $cells = $row->children();
            $label = $cells->count() > 1 ? $this->normalizeText($cells->eq(0)->text('')) : null;
            if ($label !== null) {
                $rows[$label] ??= $cells->eq(1);
            }
        });

        return $rows;
    }

    /**
     * Only the networks the actress filled in are links; the inactive icons are plain images.
     *
     * @return list<array{type: string, url: string}>
     */
    private function socialLinks(?Crawler $cell): array
    {
        $links = [];
        $cell?->filter('a[href]')->each(function (Crawler $link) use (&$links): void {
            $icon = $this->firstAttribute($link, 'img', 'src');
            $type = $icon !== null && preg_match('~/([a-z]+)_on\.png~i', $icon, $match) === 1 ? strtolower($match[1]) : 'link';
            $links[] = ['type' => $type, 'url' => trim((string) $link->attr('href'))];
        });

        return $links;
    }

    /** @return list<array<string, mixed>> the work cards: external_id, code, title, url, cover_url */
    private function works(Crawler $crawler, string $baseUrl): array
    {
        $works = [];
        $crawler->filter('div.mb_5 li a[href*="/title/"]')->each(function (Crawler $link) use (&$works, $baseUrl): void {
            $url = $this->absolute($baseUrl, (string) $link->attr('href'));
            $cover = $this->firstAttribute($link, 'img', 'src');
            $works[$url] ??= MovieMeta::filter([
                'external_id' => Fields::slug($url, 'title'),
                'code' => $this->firstText($link, 'span.badge'),
                'title' => $this->firstText($link, 'p.h6'),
                'url' => $url,
                'cover_url' => $cover === null ? null : $this->absolute($baseUrl, $cover),
            ]);
        });

        return array_values($works);
    }
}
