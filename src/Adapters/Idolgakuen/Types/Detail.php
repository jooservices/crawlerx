<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Idolgakuen\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Selectors;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
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
        $externalId = Site::externalId($request->url);
        if ($externalId === null) {
            throw new CrawlParseException(Site::NAME . ' detail URL did not contain a work ID.');
        }

        $rows = $this->rows($crawler);
        $cover = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');
        $images = $this->images($crawler, $request->url);
        $releaseDate = MovieMeta::isoDate($rows['発売日'] ?? null);
        $duration = MovieMeta::durationMinutes($rows['仕様'] ?? null);
        $price = $this->price($rows['価格'] ?? null);
        $performer = trim($rows['出演者'] ?? '');

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->firstText($crawler, Selectors::DETAIL_TITLE),
            data: [
                'code' => $rows['品番'] ?? null,
                'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION),
                'date' => $releaseDate,
                'duration' => $duration,
                'performers' => $performer === '' ? [] : [MovieMeta::performer($performer)],
                'screenshots' => $images,
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    MovieMeta::PRICE => $price,
                    'specification' => $rows['仕様'] ?? null,
                    'source_fields' => $rows,
                ]),
            ],
        );
        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /** @return array<string, string> */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, 'td.left');
            $value = $this->firstText($row, 'td.right');
            if ($label !== null && $value !== null) {
                $rows[$label] = $value;
            }
        });

        return $rows;
    }

    /** @return list<string> */
    private function images(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter(Selectors::DETAIL_IMAGES)->each(function (Crawler $link) use (&$images, $baseUrl): void {
            $href = $link->attr('href');
            if ($href !== null && $href !== '') {
                $url = $this->absolute($baseUrl, $href);
                $images[$url] = $url;
            }
        });

        return array_values($images);
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    private function price(?string $text): ?array
    {
        if ($text === null || preg_match('/([\d,]+)円/u', $text, $match) !== 1) {
            return null;
        }

        return MovieMeta::price((float) str_replace(',', '', $match[1]), 'JPY', $text);
    }
}
