<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesJson;
use JOOservices\CrawlerX\Adapters\OnePondo\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class Listing extends AbstractType implements TypeInterface
{
    use ParsesJson;

    private const DEFAULT_SPLIT_SIZE = 50;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $page = max(1, $request->page);
        $listKind = $this->listKind($request->url);

        if ($page === 1) {
            $payload = $this->fetchListPayload($listKind, 0);
        } else {
            $probe = $this->fetchListPayload($listKind, 0);
            $splitSize = $this->splitSize($probe);
            $offset = ($page - 1) * $splitSize;
            $payload = $this->fetchListPayload($listKind, $offset);
        }

        $items = $this->items($payload);
        if ($items === []) {
            throw new \RuntimeException('OnePondo listing response did not contain expected movie fields.');
        }

        $splitSize = $this->splitSize($payload);
        $offset = ($page - 1) * $splitSize;
        $totalRows = $this->totalRows($payload);
        $hasNextPage = $offset + $splitSize < $totalRows;

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: $totalRows > 0 ? (int) ceil($totalRows / $splitSize) : null,
                nextPage: $hasNextPage ? $page + 1 : null,
                nextUrl: $hasNextPage ? $this->nextListingUrl($request->url, $page + 1) : null,
                hasNextPage: $hasNextPage,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchListPayload(string $listKind, int $offset): array
    {
        $response = $this->client->get($this->listJsonUrl($listKind, $offset));

        return $this->jsonFromResponse($response->toPsrResponse(), 'OnePondo', 'listing');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<CrawlItemResultDto>
     */
    private function items(array $payload): array
    {
        $rows = $payload['Rows'] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $movieIdRaw = $row['MovieID'] ?? null;
            if (! is_string($movieIdRaw) || trim($movieIdRaw) === '') {
                continue;
            }

            $movieId = trim($movieIdRaw);
            $url = $this->normalizer->movieUrl($movieId);
            /** @var array<string, mixed> $typedRow */
            $typedRow = [];
            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $typedRow[$key] = $value;
                }
            }
            $title = $this->title($typedRow);
            $coverUrl = $this->coverUrl($typedRow);

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $movieId,
                title: $title,
                data: array_filter([
                    'code' => $movieId,
                    'cover_url' => $coverUrl,
                    'date' => $this->releaseDate($typedRow),
                ], fn(mixed $value): bool => $value !== null && $value !== ''),
            );
        }

        return array_values($items);
    }

    private function listKind(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return 'newest';
        }

        parse_str($query, $params);
        $orderRaw = $params['o'] ?? 'n';
        $order = is_string($orderRaw) ? strtolower(trim($orderRaw)) : 'n';

        return match ($order) {
            'n', 'newest' => 'newest',
            'monthly' => 'monthly',
            'weekly' => 'weekly',
            'bob' => 'bob',
            'oldest' => 'oldest',
            default => 'newest',
        };
    }

    private function listJsonUrl(string $listKind, int $offset): string
    {
        return 'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_' . $listKind . '_' . $offset . '.json';
    }

    private function nextListingUrl(string $url, int $page): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $query = [];
        $queryString = is_string($parts['query'] ?? null) ? $parts['query'] : '';
        if ($queryString !== '') {
            parse_str($queryString, $query);
        }

        $query['page'] = $page;
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '/list/';

        return $parts['scheme'] . '://' . $parts['host'] . $path . '?' . http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function title(array $row): ?string
    {
        foreach (['TitleEn', 'Title'] as $key) {
            $value = $row[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function coverUrl(array $row): ?string
    {
        foreach (['ThumbHigh', 'MovieThumb', 'ThumbMed'] as $key) {
            $value = $row[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function releaseDate(array $row): ?string
    {
        $value = $row['Release'] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = date_create_immutable(trim($value));

        return $date === false ? null : $date->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function splitSize(array $payload): int
    {
        $splitSize = $payload['SplitSize'] ?? null;

        return is_numeric($splitSize) && (int) $splitSize > 0 ? (int) $splitSize : self::DEFAULT_SPLIT_SIZE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function totalRows(array $payload): int
    {
        $totalRows = $payload['TotalRows'] ?? 0;

        return is_numeric($totalRows) ? max(0, (int) $totalRows) : 0;
    }
}
