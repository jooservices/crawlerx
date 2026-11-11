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
        $profile = $this->profile($crawler);
        $cover = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');
        $images = $this->images($crawler, $request->url);
        $releaseDate = MovieMeta::isoDate($rows['発売日'] ?? null);
        $duration = MovieMeta::durationMinutes($rows['仕様'] ?? null);
        $price = $this->price($rows['価格'] ?? null);
        $performer = trim($rows['出演者'] ?? '');
        $performerData = MovieMeta::performer($performer);
        if ($performer !== '') {
            $performerData += [
                'name_japanese' => $performer,
                'birth_date_raw' => $profile['fields']['生年月日'] ?? null,
                'size_raw' => $profile['fields']['サイズ'] ?? null,
                'raw_profile' => $profile['fields'],
                'metadata' => MovieMeta::filter([
                    'name_kana' => $profile['name_kana'],
                    'birthplace' => $profile['fields']['出身地'] ?? null,
                    'blood_type' => $profile['fields']['血液型'] ?? null,
                    'hobby' => $profile['fields']['趣味'] ?? null,
                    'special_skills' => $profile['fields']['特技'] ?? null,
                    'x_url' => $profile['x_url'],
                ]),
            ];
        }

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
                'performers' => $performer === '' ? [] : [$performerData],
                'screenshots' => $images,
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    MovieMeta::PRICE => $price,
                    'specification' => $rows['仕様'] ?? null,
                    'source_fields' => [...$rows, ...$profile['fields']],
                    'performer_name_kana' => $profile['name_kana'],
                    'performer_x_url' => $profile['x_url'],
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

    /** @return array{name_kana: ?string, fields: array<string, string>, x_url: ?string} */
    private function profile(Crawler $crawler): array
    {
        $fields = [];
        $xUrl = null;
        $profileNode = $crawler->filter(Selectors::PERFORMER_PROFILE);
        if ($profileNode->count() > 0) {
            $node = $profileNode->getNode(0);
            if ($node === null) {
                return [
                    'name_kana' => $this->firstText($crawler, Selectors::PERFORMER_NAME_KANA),
                    'fields' => [],
                    'x_url' => null,
                ];
            }

            $line = '';
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && strtolower($child->tagName) === 'br') {
                    $this->addProfileField($line, $fields);
                    $line = '';

                    continue;
                }

                $line .= $child->textContent;
                if ($child instanceof \DOMElement && strtolower($child->tagName) === 'a') {
                    $href = $child->getAttribute('href');
                    $xUrl = $href !== '' ? $href : null;
                }
            }
            $this->addProfileField($line, $fields);
        }

        return [
            'name_kana' => $this->firstText($crawler, Selectors::PERFORMER_NAME_KANA),
            'fields' => $fields,
            'x_url' => $xUrl,
        ];
    }

    /** @param array<string, string> $fields */
    private function addProfileField(string $line, array &$fields): void
    {
        if (preg_match('/^([^：:]+)[：:]\s*(.*)$/u', trim($line), $match) === 1) {
            $fields[trim($match[1])] = trim($match[2]);
        }
    }

    /** @return list<array{url: string, thumbnail_url: ?string}> */
    private function images(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter(Selectors::DETAIL_IMAGES)->each(function (Crawler $link) use (&$images, $baseUrl): void {
            $href = $link->attr('href');
            if ($href !== null && $href !== '') {
                $url = $this->absolute($baseUrl, $href);
                $thumbnail = $this->firstAttribute($link, 'img[src]', 'src');
                $images[$url] ??= [
                    'url' => $url,
                    'thumbnail_url' => $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
                ];
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
