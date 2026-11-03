<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Fields;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client, private readonly SiteConfig $config)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $id = $this->config->entityId($request->url, $this->config->detailPath);
        $response = $this->client->get($request->url);
        $dom = new Crawler($this->htmlFromResponse($response->toPsrResponse(), $this->config->maker, 'detail'));
        $fields = new Fields($this->config);
        $body = $dom->filter('.p-workPage');
        $rows = $fields->rows($body, '.p-workPage__table > .item');
        $images = $fields->images($dom, '.p-slider.-work .swiper-slide');
        $genres = isset($rows['ジャンル']) ? $fields->links($rows['ジャンル'], 'a[href]') : [];
        $formats = $this->formats($rows, $fields);
        $item = MovieDto::item($this->config->baseUrl . $this->config->detailPath . $id, $id, $fields->text($body, '.p-workPage__title'), [
            'code' => $id,
            'description' => $fields->text($body, '.p-workPage__text'),
            'date' => isset($rows['発売日']) ? $fields->date($rows['発売日']->text('')) : null,
            'duration' => $formats[0]['duration'] ?? null,
            'cover_url' => $images[0] ?? null,
            'screenshots' => $images,
            'performers' => isset($rows['女優']) ? $fields->performers($rows['女優']) : [],
            'tags' => array_column($genres, 'name'),
            'metadata' => [
                'maker' => $this->config->maker,
                'genres' => $genres,
                'series' => isset($rows['シリーズ']) ? $fields->links($rows['シリーズ'], 'a[href]') : [],
                ...(isset($rows['レーベル']) ? ['label' => $fields->links($rows['レーベル'], 'a[href]')] : []),
                'release_links' => isset($rows['発売日']) ? $fields->links($rows['発売日'], 'a[href]') : [],
                'director' => $this->director($rows, $fields),
                'formats' => $formats,
                'sample_video_url' => $this->videoAttribute($body, 'src'),
                'sample_video_poster_url' => $this->videoAttribute($body, 'poster'),
                'purchase_links' => $fields->links($body, '.p-workPage__side a.box[href]'),
                'release_note' => $fields->text($body, '.p-workPage__side .note'),
                'source_fields' => $this->sourceFields($rows),
            ],
        ]);
        $this->assertUsableMovieDetail($item, $this->config->maker);

        return $item;
    }

    /** @param array<string, Crawler> $rows
     * @return list<array<string, mixed>>
     */
    private function formats(array $rows, Fields $fields): array
    {
        $formats = [];
        foreach (['品番' => 'code', '収録時間' => 'duration', '価格' => 'price'] as $label => $key) {
            if (! isset($rows[$label])) {
                continue;
            }
            foreach ($rows[$label]->filter('.td .item') as $element) {
                $node = new Crawler($element);
                $format = $fields->text($node, 'span') ?? 'default';
                $value = trim(str_replace($format, '', $node->text('')));
                $formats[$format]['format'] = $format;
                $formats[$format][$key] = $key === 'duration' ? (int) $value : $value;
            }
        }

        return array_values($formats);
    }

    /** @param array<string, Crawler> $rows */
    private function director(array $rows, Fields $fields): ?string
    {
        $director = isset($rows['監督']) ? $fields->text($rows['監督'], '.td') : null;

        return $director === '---' ? null : $director;
    }

    private function videoAttribute(Crawler $node, string $attribute): ?string
    {
        $video = $node->filter('video');
        $value = $video->count() > 0 ? $video->first()->attr($attribute) : null;

        return $value !== null && $value !== '' ? $this->config->absolute($value) : null;
    }

    /** @param array<string, Crawler> $rows
     * @return array<string, string>
     */
    private function sourceFields(array $rows): array
    {
        return array_map(static fn(Crawler $row): string => trim($row->text('')), $rows);
    }
}
