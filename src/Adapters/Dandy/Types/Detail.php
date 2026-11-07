<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dandy\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Dandy\Fields;
use JOOservices\CrawlerX\Adapters\Dandy\Selectors;
use JOOservices\CrawlerX\Adapters\Dandy\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    protected function siteLabel(): string
    {
        return 'DANDY GROUP';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $slug = Site::workSlug($request->url);
        if ($slug === null) {
            throw new CrawlParseException('DANDY GROUP detail URL is not a work page.');
        }

        $dom = $this->fetchCrawler($request);
        $rows = $this->rows($dom);
        $images = Fields::images($dom, Selectors::GALLERY_IMAGES);
        $cover = $images[0] ?? null;
        $genres = $this->genres($rows['ジャンル'] ?? null);
        $label = $this->label($dom);
        $director = $this->director($rows['監督'] ?? null);

        $item = MovieDto::item(Site::absolute($request->url), $slug, Fields::text($dom, Selectors::TITLE), MovieMeta::filter([
            'code' => Fields::clean($rows['品番']['text'] ?? null) ?? strtoupper($slug),
            'description' => Fields::text($dom, Selectors::DESCRIPTION),
            'date' => Fields::isoDate($rows['動画配信日']['text'] ?? null) ?? Fields::isoDate($rows['DVD発売日']['text'] ?? null),
            'duration' => MovieMeta::durationMinutes($rows['時間']['text'] ?? null),
            'cover_url' => $cover['url'] ?? $this->ogImage($dom),
            'screenshots' => $images,
            'tags' => array_column($genres, 'name'),
            MovieMeta::MAKER => Fields::clean($rows['メーカー']['text'] ?? null),
            MovieMeta::LABEL => $label['name'] ?? null,
            'label_slug' => Site::labelSlug($request->url),
            'label_url' => Site::absolute('/' . Site::labelSlug($request->url) . '/'),
            'label_logo_url' => $this->labelLogo($dom),
            'label_color' => $this->labelColor($dom),
            MovieMeta::DIRECTOR => $director['name'] ?? null,
            'director_url' => $director['url'] ?? null,
            'stream_date' => Fields::isoDate($rows['動画配信日']['text'] ?? null),
            'dvd_release_date' => Fields::isoDate($rows['DVD発売日']['text'] ?? null),
            MovieMeta::PRICE => $this->price($rows['販売価格']['text'] ?? null),
            'genres' => $genres,
            'cover_thumbnail_url' => $cover['thumbnail_url'] ?? null,
            MovieMeta::SAMPLE_VIDEO_URL => $this->sampleVideo($dom),
            'purchase_links' => $this->stores($dom),
            'purchase_note' => Fields::text($dom, Selectors::STORE_NOTE),
            'source_fields' => array_map(static fn(array $row): string => $row['text'], $rows),
        ]));
        $this->assertUsableMovieDetail($item, 'DANDY GROUP');

        return $item;
    }

    /**
     * Labelled `dt`/`dd` rows keyed by their label (メーカー, 動画配信日, 時間, 品番, ジャンル, 監督…).
     *
     * @return array<string, array{text: string, node: Crawler}>
     */
    private function rows(Crawler $dom): array
    {
        $rows = [];
        foreach ($dom->filter(Selectors::ROWS) as $element) {
            $row = new Crawler($element);
            $label = Fields::text($row, Selectors::ROW_LABEL);
            $value = $row->filter(Selectors::ROW_VALUE);
            if ($label === null || $value->count() === 0) {
                continue;
            }
            $links = $value->first()->filter('a')->each(static fn(Crawler $a): string => trim($a->text('')));
            $rows[$label] = [
                'text' => count($links) > 1 ? implode(' ', $links) : (Fields::clean($value->first()->text('')) ?? ''),
                'node' => $value->first(),
            ];
        }

        return $rows;
    }

    /**
     * @param array{text: string, node: Crawler}|null $row
     * @return list<array{name: string, url: string, slug: string}>
     */
    private function genres(?array $row): array
    {
        $genres = [];
        if ($row === null) {
            return $genres;
        }
        foreach ($row['node']->filter('a[href]') as $element) {
            $link = new Crawler($element);
            $url = Site::absolute((string) $link->attr('href'));
            $name = Fields::clean($link->text(''));
            if ($name !== null) {
                $genres[$url] = ['name' => $name, 'url' => $url, 'slug' => rawurldecode(basename(rtrim($url, '/')))];
            }
        }

        return array_values($genres);
    }

    /**
     * @param array{text: string, node: Crawler}|null $row
     * @return array{name: string, url: ?string}|null
     */
    private function director(?array $row): ?array
    {
        if ($row === null || $row['text'] === '---') {
            return null;
        }
        $link = $row['node']->filter('a[href]');

        return [
            'name' => $row['text'],
            'url' => $link->count() > 0 ? Site::absolute((string) $link->first()->attr('href')) : null,
        ];
    }

    /**
     * The page title reads `<work> | <label name> | DANDY GROUP | <code>`.
     *
     * @return array{name: string}|null
     */
    private function label(Crawler $dom): ?array
    {
        $title = $dom->filter('head > title');
        $parts = $title->count() > 0 ? array_map('trim', explode(' | ', $title->first()->text(''))) : [];
        if (count($parts) < 4) {
            return null;
        }

        return ['name' => $parts[count($parts) - 3]];
    }

    private function labelLogo(Crawler $dom): ?string
    {
        $logo = $dom->filter(Selectors::LABEL_LOGO);

        return $logo->count() > 0 ? (Fields::image($logo->first())['url'] ?? null) : null;
    }

    private function labelColor(Crawler $dom): ?string
    {
        $style = (string) $dom->filter('html')->attr('style');

        return preg_match('/--maker-color:\s*(#[0-9a-f]{3,8})/i', $style, $match) === 1 ? $match[1] : null;
    }

    private function ogImage(Crawler $dom): ?string
    {
        $meta = $dom->filter(Selectors::OG_IMAGE);

        return $meta->count() > 0 ? Fields::clean($meta->first()->attr('content')) : null;
    }

    private function sampleVideo(Crawler $dom): ?string
    {
        $source = $dom->filter(Selectors::SAMPLE_VIDEO);

        return $source->count() > 0 ? Fields::clean($source->first()->attr('src')) : null;
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    private function price(?string $text): ?array
    {
        return $text !== null && preg_match('/(\d[\d,]*)\s*円/u', $text, $match) === 1
            ? MovieMeta::price((float) str_replace(',', '', $match[1]), 'JPY', $text)
            : null;
    }

    /** @return list<array{name: ?string, store: ?string, type: string, url: string}> */
    private function stores(Crawler $dom): array
    {
        $links = [];
        foreach ($dom->filter(Selectors::STORE_LINKS) as $element) {
            $link = new Crawler($element);
            $url = Fields::clean($link->attr('href'));
            if ($url === null) {
                continue;
            }
            $logo = $link->filter(Selectors::STORE_LOGO);
            $links[] = [
                'name' => Fields::text($link, Selectors::STORE_LABEL),
                'store' => $logo->count() > 0 ? Fields::clean($logo->first()->attr('alt')) : null,
                'type' => str_contains((string) $link->attr('class'), 'dvd') ? 'dvd' : 'video',
                'url' => $url,
            ];
        }

        return $links;
    }
}
