<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesJson;
use JOOservices\CrawlerX\Adapters\OnePondo\MovieRowFields;
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
        private readonly MovieRowFields $fields = new MovieRowFields(),
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
            title: $this->fields->title($detail) ?? $movieId,
            data: [...$this->fields->read($detail), 'screenshots' => $this->screenshots($gallery)],
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
}
