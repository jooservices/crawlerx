<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Dahlia\Fields;
use JOOservices\CrawlerX\Adapters\Dahlia\Selectors;
use JOOservices\CrawlerX\Adapters\Dahlia\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const ROW_BIRTHDAY = '誕生日';

    private const ROW_HEIGHT = '身長';

    private const ROW_SIZE = 'スリーサイズ';

    private const ROW_BIRTHPLACE = '出身地';

    private const ROW_HOBBY = '趣味';

    private const ROW_SKILL = '特技';

    private const ROW_DURATION = '収録時間';

    private const ROW_DATES = ['発売日', '配信日', '配信開始日'];

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'performer_detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $fields = new Fields();
        $profile = $fields->rows($crawler, Selectors::PERFORMER_ROWS);
        $romaji = $this->firstText($crawler, Selectors::PERFORMER_ROMAJI);
        $heading = $this->firstText($crawler, Selectors::PERFORMER_NAME);
        $name = $heading !== null && $romaji !== null ? $this->normalizeText(substr($heading, 0, -strlen($romaji))) : $heading;
        $photo = $fields->image($crawler, Selectors::PERFORMER_PHOTO, $request->url);

        $item = PerformerDto::item(
            url: $request->url,
            externalId: Site::performerId($request->url),
            title: $name,
            data: [
                'name_japanese' => $name,
                'aliases' => $romaji === null ? [] : [$romaji],
                'profile_image_url' => $photo,
                'birth_date_raw' => $profile[self::ROW_BIRTHDAY] ?? null,
                'height_raw' => $profile[self::ROW_HEIGHT] ?? null,
                'size_raw' => $profile[self::ROW_SIZE] ?? null,
                'raw_profile' => $profile,
                'metadata' => MovieMeta::filter([
                    'name_romaji' => $romaji,
                    'birth_date' => MovieMeta::isoDate($profile[self::ROW_BIRTHDAY] ?? null),
                    'birthplace' => $profile[self::ROW_BIRTHPLACE] ?? null,
                    'hobby' => $profile[self::ROW_HOBBY] ?? null,
                    'skill' => $profile[self::ROW_SKILL] ?? null,
                    'images' => $photo === null ? [] : [$photo],
                    'works' => $this->works($crawler, $request->url, $fields),
                ]),
            ],
        );

        $this->assertUsablePerformerDetail($item, Site::NAME);

        return $item;
    }

    /**
     * The performer's own latest works shown on the page.
     *
     * @return list<array<string, mixed>>
     */
    private function works(Crawler $crawler, string $baseUrl, Fields $fields): array
    {
        $works = [];
        $crawler->filter(Selectors::PERFORMER_WORK)->each(function (Crawler $card) use (&$works, $baseUrl, $fields): void {
            $url = $fields->link($card, Selectors::PERFORMER_WORK_LINK, $baseUrl);
            $externalId = $url === null ? null : Site::workId($url);
            if ($url === null || $externalId === null) {
                return;
            }

            $rows = $fields->rows($card, Selectors::PERFORMER_WORK_ROWS);
            $dates = array_values(array_filter(array_map(
                static fn(string $label): ?string => MovieMeta::isoDate($rows[$label] ?? null),
                self::ROW_DATES,
            ), static fn(?string $date): bool => $date !== null));
            $works[] = MovieMeta::filter([
                'external_id' => $externalId,
                'code' => Site::code($externalId),
                'url' => $url,
                'title' => $this->firstText($card, Selectors::PERFORMER_WORK_LINK),
                'cover_url' => $fields->image($card, Selectors::PERFORMER_WORK_COVER, $baseUrl),
                'date' => $dates[0] ?? null,
                'duration' => MovieMeta::durationMinutes($rows[self::ROW_DURATION] ?? null),
                MovieMeta::SAMPLE_VIDEO_URL => $fields->link($card, Selectors::SAMPLE_VIDEO, $baseUrl),
                'release_notes' => $fields->releaseNotes($card, Selectors::PERFORMER_WORK_RELEASE_NOTES),
                'purchase_links' => $fields->purchaseLinks($card, Selectors::PERFORMER_WORK_PURCHASE_LINKS),
                'source_fields' => $rows,
            ]);
        });

        return $works;
    }
}
