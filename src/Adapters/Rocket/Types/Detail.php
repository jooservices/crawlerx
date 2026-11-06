<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Rocket\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Rocket\Fields;
use JOOservices\CrawlerX\Adapters\Rocket\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * ROCKET work page: the product info table (cast, director, genres, series, code, runtime, release dates), the
 * introduction, jacket, sample images and video, store links and the WordPress post data. The header, footer,
 * banners and social links are not the work's data.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    private const TERM_TAXONOMIES = ['actress', 'genre', 'director', 'series'];

    protected function siteLabel(): string
    {
        return 'ROCKET';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $id = Site::entityId($request->url);
        $dom = $this->fetchCrawler($request);
        $article = $dom->filter('article.single-works');
        if ($article->count() === 0) {
            throw new CrawlParseException('ROCKET detail page did not contain a work article.');
        }

        $fields = new Fields();
        $rows = $fields->rows($article);
        $linksOf = static fn(string $label): array => isset($rows[$label]) ? $fields->links($rows[$label]) : [];
        $textOf = static fn(string ...$labels): ?string => $fields->value($rows, ...$labels);

        $directors = $linksOf('監督名');
        $genres = $linksOf('ジャンル名');
        $series = $linksOf('シリーズ名');
        $releaseText = $textOf('DVD発売日', '発売日');
        $streamText = $textOf('先行配信日');
        $schema = $this->schemaDates($dom);
        $item = MovieDto::item(Site::detailUrl($id), $id, $fields->text($article, 'header h1'), MovieMeta::filter([
            'code' => $textOf('品番'),
            'description' => $this->introduction($article, $fields),
            'date' => MovieMeta::isoDate($releaseText),
            'duration' => MovieMeta::durationMinutes($textOf('収録時間')),
            'cover_url' => $fields->image($article, 'article > header img'),
            'screenshots' => $this->screenshots($article, $fields),
            'performers' => isset($rows['女優名']) ? $fields->performers($rows['女優名']) : [],
            'tags' => array_column($genres, 'name'),
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => Site::MAKER,
                MovieMeta::DIRECTOR => $directors !== [] ? implode(', ', array_column($directors, 'name')) : null,
                MovieMeta::SERIES => $series !== [] ? implode(', ', array_column($series, 'name')) : null,
                MovieMeta::SAMPLE_VIDEO_URL => $fields->image($article, '.sample-movie iframe'),
                MovieMeta::RELEASE_DATE_RAW => $releaseText,
                MovieMeta::POSTED_DATE => $schema['datePublished'] ?? null,
                'modified_date' => $schema['dateModified'] ?? null,
                'stream_release_date' => MovieMeta::isoDate($streamText),
                'genres' => $genres,
                'director_links' => $directors,
                'series_links' => $series,
                'purchase_links' => $this->purchaseLinks($article, $fields),
                'post_id' => $this->postId($article),
                'term_ids' => $this->termIds($article),
                'source_fields' => $fields->sourceFields($rows),
            ]),
        ]));
        $this->assertUsableMovieDetail($item, 'ROCKET');

        return $item;
    }

    private function introduction(Crawler $article, Fields $fields): ?string
    {
        $paragraphs = $article->filter('.work-introduction p')->each(static fn(Crawler $paragraph): ?string => $fields->text($paragraph, 'p'));
        $text = implode("\n", array_filter($paragraphs, static fn(?string $text): bool => $text !== null));

        return $text !== '' ? $text : $fields->text($article, '.work-introduction');
    }

    /** @return list<array{url: string, thumbnail_url: ?string}> */
    private function screenshots(Crawler $article, Fields $fields): array
    {
        $thumbnails = $fields->images($article, '.work-sample-images img');
        $full = $fields->images($article, '#modal-sample-images .slide img');
        $screenshots = [];
        foreach ($thumbnails as $index => $thumbnail) {
            $screenshots[] = ['url' => $full[$index] ?? $thumbnail, 'thumbnail_url' => $thumbnail];
        }

        return $screenshots;
    }

    /** @return list<array{group: ?string, name: string, url: string}> */
    private function purchaseLinks(Crawler $article, Fields $fields): array
    {
        $links = [];
        foreach ($article->filter('.work-links .grid > div') as $element) {
            $group = new Crawler($element);
            $name = $fields->text($group, 'h3');
            foreach ($fields->links($group, 'li a[href]') as $link) {
                $links[] = ['group' => $name, 'name' => $link['name'], 'url' => $link['url']];
            }
        }

        return $links;
    }

    private function postId(Crawler $article): ?int
    {
        return preg_match('/(?:^|\s)post-(\d+)(?:\s|$)/', $article->attr('class') ?? '', $match) === 1 ? (int) $match[1] : null;
    }

    /** @return array<string, list<int>> */
    private function termIds(Crawler $article): array
    {
        $ids = [];
        preg_match_all('/(?:^|\s)works_(' . implode('|', self::TERM_TAXONOMIES) . ')-(\d+)(?=\s|$)/', $article->attr('class') ?? '', $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $ids[$match[1]][] = (int) $match[2];
        }

        return $ids;
    }

    /** @return array<string, string> ISO dates of the WebPage schema.org node */
    private function schemaDates(Crawler $dom): array
    {
        $dates = [];
        foreach ($dom->filter('script[type="application/ld+json"]') as $element) {
            $data = json_decode((new Crawler($element))->text(''), true);
            $graph = is_array($data) && is_array($data['@graph'] ?? null) ? $data['@graph'] : [];
            foreach ($graph as $node) {
                foreach (['datePublished', 'dateModified'] as $key) {
                    $date = is_array($node) && is_string($node[$key] ?? null) ? MovieMeta::isoDate($node[$key]) : null;
                    if ($date !== null) {
                        $dates[$key] = $date;
                    }
                }
            }
        }

        return $dates;
    }
}
