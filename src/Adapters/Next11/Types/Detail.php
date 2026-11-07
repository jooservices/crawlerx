<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Next11\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Next11\Fields;
use JOOservices\CrawlerX\Adapters\Next11\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    public function __construct(
        CrawlHttpClient $client,
        private readonly Fields $fields = new Fields(),
    ) {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return 'next11';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $crawler->filter(Selectors::DETAIL_ROWS)->count() > 0
            ? $this->fields->rows($crawler->filter(Selectors::DETAIL_ROWS)->first())
            : [];
        $schema = $this->movieSchema($crawler);
        $code = $this->rowText($rows, '品番') ?? $this->string($schema['identifier'] ?? null);
        $genres = isset($rows['ジャンル']) ? $this->fields->links($rows['ジャンル']) : [];
        $label = isset($rows['レーベル']) ? $this->fields->entity($rows['レーベル']) : null;
        $series = isset($rows['シリーズ']) ? $this->fields->entity($rows['シリーズ']) : null;
        $maker = isset($rows['メーカー']) ? $this->fields->entity($rows['メーカー']) : null;
        $company = $schema['productionCompany'] ?? null;
        $screenshots = $this->screenshots($crawler);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $this->fields->productId($request->url),
            title: $this->rowText($rows, 'タイトル') ?? $this->heading($crawler, $code),
            data: [
                'code' => $code,
                'description' => $this->description($crawler, $schema),
                'date' => $this->date($rows, $schema),
                'duration' => MovieMeta::durationMinutes($this->rowText($rows, '収録時間')) ?? $this->isoDuration($schema['duration'] ?? null),
                'cover_url' => $this->cover($crawler),
                'screenshots' => $screenshots,
                'performers' => isset($rows['出演']) ? $this->fields->links($rows['出演']) : [],
                'tags' => array_column($genres, 'name'),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $maker['name'] ?? (is_array($company) ? $this->string($company['name'] ?? null) : null),
                    'maker_url' => $maker['url'] ?? null,
                    MovieMeta::LABEL => $label['name'] ?? null,
                    'label_id' => $label['external_id'] ?? null,
                    'label_url' => $label['url'] ?? null,
                    MovieMeta::SERIES => $series['name'] ?? null,
                    'series_id' => $series['external_id'] ?? null,
                    'series_url' => $series['url'] ?? null,
                    MovieMeta::DIRECTOR => isset($rows['監督']) ? $this->fields->text($rows['監督']) : null,
                    'genres' => $genres,
                    'cover_medium_url' => $this->fields->resolve($this->string($schema['image'] ?? null)),
                    MovieMeta::SAMPLE_VIDEO_URL => $this->sampleVideo($crawler),
                    'purchase_links' => $this->purchaseLinks($crawler),
                    'badges' => $this->badges($crawler),
                    'release_note' => $this->releaseNote($crawler),
                    'source_fields' => $this->fields->sourceFields($rows),
                ]),
            ],
        );
        $this->assertUsableMovieDetail($item, 'Next11');

        return $item;
    }

    /** @param array<string, Crawler> $rows */
    private function rowText(array $rows, string $label): ?string
    {
        return isset($rows[$label]) ? $this->fields->text($rows[$label]) : null;
    }

    /** @return array<string, mixed> the schema.org Movie block */
    private function movieSchema(Crawler $crawler): array
    {
        foreach ($crawler->filter(Selectors::JSON_LD) as $script) {
            $data = json_decode($script->textContent, true);
            if (is_array($data) && ($data['@type'] ?? null) === 'Movie') {
                return array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
            }
        }

        return [];
    }

    private function heading(Crawler $crawler, ?string $code): ?string
    {
        $heading = $crawler->filter(Selectors::DETAIL_TITLE);
        $title = $heading->count() > 0 ? $this->fields->text($heading->first()) : null;
        $prefix = $code === null ? '' : '[' . $code . '] ';

        return $title !== null && $prefix !== '' && str_starts_with($title, $prefix) ? substr($title, strlen($prefix)) : $title;
    }

    /** @param array<string, mixed> $schema */
    private function description(Crawler $crawler, array $schema): ?string
    {
        $review = $crawler->filter(Selectors::DETAIL_REVIEW);

        return ($review->count() > 0 ? $this->fields->text($review->first()) : null) ?? $this->string($schema['description'] ?? null);
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @param  array<string, mixed>  $schema
     */
    private function date(array $rows, array $schema): ?string
    {
        $time = isset($rows['発売日']) ? $rows['発売日']->filter('time[datetime]') : null;
        $value = $time !== null && $time->count() > 0 ? $time->first()->attr('datetime') : $this->rowText($rows, '発売日');

        return MovieMeta::isoDate($value) ?? MovieMeta::isoDate($this->string($schema['dateCreated'] ?? null));
    }

    private function isoDuration(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?$/', $value, $match) === 1
            ? ((int) ($match[1] ?? 0) * 60) + (int) ($match[2] ?? 0)
            : null;
    }

    private function cover(Crawler $crawler): ?string
    {
        $link = $crawler->filter(Selectors::DETAIL_COVER_LINK);
        $image = $crawler->filter(Selectors::DETAIL_COVER_IMAGE);
        $og = $crawler->filter(Selectors::OG_IMAGE);

        return $this->fields->resolve($link->count() > 0 ? $link->first()->attr('href') : null)
            ?? $this->fields->resolve($image->count() > 0 ? $image->first()->attr('src') : null)
            ?? $this->fields->resolve($og->count() > 0 ? $og->first()->attr('content') : null);
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> every sample still, full size plus thumbnail, in page order */
    private function screenshots(Crawler $crawler): array
    {
        $stills = [];
        foreach ($crawler->filter(Selectors::DETAIL_SAMPLE_LINKS) as $element) {
            $link = new Crawler($element);
            $url = $this->fields->resolve($link->attr('href'));
            if ($url === null || isset($stills[$url])) {
                continue;
            }
            $image = $link->filter('img');
            $stills[$url] = [
                'url' => $url,
                'thumbnail_url' => $image->count() > 0 ? $this->fields->resolve($image->first()->attr('src')) : null,
                'position' => count($stills) + 1,
            ];
        }

        return array_values($stills);
    }

    private function sampleVideo(Crawler $crawler): ?string
    {
        $frame = $crawler->filter(Selectors::SAMPLE_VIDEO);

        return $frame->count() > 0 ? $this->fields->resolve($frame->first()->attr('src')) : null;
    }

    /** @return list<array<string, mixed>> {type, label, note, url} per FANZA purchase card */
    private function purchaseLinks(Crawler $crawler): array
    {
        $links = [];
        foreach ($crawler->filter(Selectors::PURCHASE_CARDS) as $element) {
            $card = new Crawler($element);
            $url = $this->fields->resolve($card->attr('href'));
            if ($url === null) {
                continue;
            }
            $title = $card->filter('.fanza_purchase_title');
            $note = $card->filter('.fanza_purchase_sub');
            $links[] = MovieMeta::filter([
                'type' => $card->filter('.fanza_icon_dvd')->count() > 0 ? 'dvd' : 'video',
                'label' => $title->count() > 0 ? $this->fields->text($title->first()) : null,
                'note' => $note->count() > 0 ? $this->fields->text($note->first()) : null,
                'url' => $url,
            ]);
        }

        return $links;
    }

    /** @return list<string> */
    private function badges(Crawler $crawler): array
    {
        return array_values(array_filter($crawler->filter(Selectors::DETAIL_BADGES)->each(
            fn(Crawler $image): string => trim((string) $image->attr('alt')),
        ), static fn(string $alt): bool => $alt !== ''));
    }

    private function releaseNote(Crawler $crawler): ?string
    {
        $note = $crawler->filter(Selectors::DETAIL_NOTE);

        return $note->count() > 0 ? $this->fields->text($note->first()) : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
