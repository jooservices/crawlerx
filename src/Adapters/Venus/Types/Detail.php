<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Venus\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Venus\Selectors;
use JOOservices\CrawlerX\Adapters\Venus\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $this->rows($crawler);
        $code = Site::code($rows['品番・分数'] ?? null);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE);
        $coverLink = $this->firstAttribute($crawler, Selectors::DETAIL_COVER_LINK, 'href');
        $coverImage = $this->firstAttribute($crawler, Selectors::DETAIL_COVER_IMAGE, 'src');
        $cover = $coverLink ?? $coverImage;
        $performers = $this->performers($rows['出演女優'] ?? null);

        return MovieDto::item(
            url: $request->url,
            externalId: $code,
            title: $title,
            data: MovieMeta::filter([
                'code' => $code,
                'description' => $rows['作品紹介'] ?? null,
                'date' => MovieMeta::isoDate($rows['発売日'] ?? null),
                'duration' => MovieMeta::durationMinutes($rows['品番・分数'] ?? null),
                'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                'performers' => array_map(static fn(string $name): array => MovieMeta::performer($name), $performers),
                'screenshots' => [],
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    MovieMeta::LABEL => $rows['レーベル'] ?? null,
                    'source_fields' => $rows,
                ]),
            ]),
        );
    }

    /** @return array<string, string> */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, 'dt');
            $value = $this->firstText($row, 'dd');
            if ($label !== null && $value !== null) {
                $rows[$label] = $value;
            }
        });

        return $rows;
    }

    /** @return list<string> */
    private function performers(?string $text): array
    {
        if ($text === null) {
            return [];
        }

        $names = preg_split('/[、,，\/／]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $names === false
            ? []
            : array_values(array_filter(array_map('trim', $names), static fn(string $name): bool => $name !== ''));
    }
}
