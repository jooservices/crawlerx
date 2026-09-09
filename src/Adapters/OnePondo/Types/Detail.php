<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesJson;
use JOOservices\CrawlerX\Adapters\OnePondo\Selectors;
use JOOservices\CrawlerX\Adapters\OnePondo\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class Detail extends AbstractType implements TypeInterface
{
    use ParsesJson;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $movieId = $this->movieId($request->url);
        if ($movieId === null) {
            throw new \RuntimeException('OnePondo detail URL did not contain a movie id.');
        }

        $detailUrl = str_contains($request->url, 'movie_details')
            ? $request->url
            : $this->detailJsonUrl($movieId);
        $detailResponse = $this->client->get($detailUrl);
        $detail = $this->jsonFromResponse($detailResponse->toPsrResponse(), 'OnePondo', 'detail');

        $gallery = null;
        try {
            $galleryResponse = $this->client->get($this->galleryJsonUrl($movieId));
            $gallery = $this->tryJsonFromResponse($galleryResponse->toPsrResponse());
        } catch (NetworkConnectionException) {
            $gallery = null;
        }

        $item = MovieDto::item(
            url: $this->normalizer->movieUrl($movieId),
            externalId: $movieId,
            title: $this->title($detail) ?? $movieId,
            data: [
                'code' => $movieId,
                'cover_url' => $this->coverUrl($detail),
                'description' => $this->description($detail),
                'date' => $this->releaseDate($detail),
                'duration' => $this->durationMinutes($detail),
                'maker' => '1Pondo',
                'performers' => $this->performers($detail),
                'tags' => $this->tags($detail),
                'screenshots' => $this->screenshots($gallery),
                'metadata' => [
                    'series' => $this->stringField($detail, 'SeriesEn') ?? $this->stringField($detail, 'Series'),
                    'rating' => is_numeric($detail['AvgRating'] ?? null) ? (float) $detail['AvgRating'] : null,
                    'sample_streams' => $this->sampleStreams($detail),
                ],
            ],
        );

        $this->assertUsableDetail($item, 'OnePondo');

        return $item;
    }

    private function movieId(string $url): ?string
    {
        if (preg_match(Selectors::MOVIE_URL_PATTERN, $url, $match) === 1) {
            return $match[1];
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $basename = preg_replace('/\.json$/i', '', trim(basename(trim($path, '/')), '/')) ?? '';

        return preg_match(Selectors::MOVIE_ID_PATTERN, $basename, $match) === 1 ? $match[1] : null;
    }

    private function detailJsonUrl(string $movieId): string
    {
        return 'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/' . $movieId . '.json';
    }

    private function galleryJsonUrl(string $movieId): string
    {
        return 'https://en.1pondo.tv/dyn/dla/json/movie_gallery/' . $movieId . '.json';
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function title(array $detail): ?string
    {
        return $this->stringField($detail, 'TitleEn') ?? $this->stringField($detail, 'Title');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function description(array $detail): ?string
    {
        return $this->stringField($detail, 'DescEn') ?? $this->stringField($detail, 'Desc');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function coverUrl(array $detail): ?string
    {
        return $this->stringField($detail, 'ThumbHigh')
            ?? $this->stringField($detail, 'MovieThumb')
            ?? $this->stringField($detail, 'ThumbMed');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function releaseDate(array $detail): ?string
    {
        $value = $this->stringField($detail, 'Release');
        if ($value === null) {
            return null;
        }

        $date = date_create_immutable($value);

        return $date === false ? null : $date->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function durationMinutes(array $detail): ?int
    {
        $seconds = $detail['Duration'] ?? null;
        if (! is_numeric($seconds) || (int) $seconds <= 0) {
            return null;
        }

        return max(1, (int) round(((int) $seconds) / 60));
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<string>
     */
    private function performers(array $detail): array
    {
        return $this->stringList($detail['ActressesEn'] ?? $detail['ActressesJa'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<string>
     */
    private function tags(array $detail): array
    {
        return $this->stringList($detail['UCNAMEEn'] ?? $detail['UCNAME'] ?? []);
    }

    /**
     * @param  array<string, mixed>|null  $gallery
     * @return list<array{url: string, thumbnail_url: null, position: int, metadata: array<string, mixed>}>
     */
    private function screenshots(?array $gallery): array
    {
        if ($gallery === null) {
            return [];
        }

        $rows = $gallery['Rows'] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $screenshots = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $image = $row['Img'] ?? null;
            if (! is_string($image) || trim($image) === '' || ! str_contains($image, '/sample/')) {
                continue;
            }

            if (($row['Protected'] ?? false) === true) {
                continue;
            }

            $url = $this->normalizer->galleryImageUrl($image);
            $screenshots[$url] = [
                'url' => $url,
                'thumbnail_url' => null,
                'position' => count($screenshots) + 1,
                'metadata' => [
                    'source' => 'onepondo_gallery',
                ],
            ];
        }

        return array_values($screenshots);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<array{quality: string, url: string}>
     */
    private function sampleStreams(array $detail): array
    {
        $files = $detail['SampleFiles'] ?? null;
        if (! is_array($files)) {
            return [];
        }

        $streams = [];
        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $url = $file['URL'] ?? null;
            $quality = $file['FileName'] ?? null;
            if (! is_string($url) || trim($url) === '' || ! is_string($quality) || trim($quality) === '') {
                continue;
            }

            $streams[] = [
                'quality' => trim($quality),
                'url' => trim($url),
            ];
        }

        return $streams;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn(mixed $entry): string => is_string($entry) ? trim($entry) : '', $value),
            fn(string $entry): bool => $entry !== '',
        )));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringField(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
