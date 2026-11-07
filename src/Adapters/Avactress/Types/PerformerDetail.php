<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avactress\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Avactress\Fields;
use JOOservices\CrawlerX\Adapters\Avactress\Selectors;
use JOOservices\CrawlerX\Adapters\Avactress\Site;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A performer profile (/<id>/<name>): measurements, birthday, blood type, birthplace, hobbies and the performer's
 * works (100 per page, `?page=N` for the rest), each with its cover images and sample movie.
 */
final class PerformerDetail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const ROW_BUST = 'バスト';

    private const ROW_WAIST = 'ウエスト';

    private const ROW_HIP = 'ヒップ';

    private const ROW_HEIGHT = '身長';

    private const ROW_BIRTHDAY = '誕生日';

    private const ROW_BLOOD_TYPE = '血液型';

    private const ROW_BIRTHPLACE = '出身';

    private const ROW_HOBBY = '趣味';

    private const HOBBY_SEPARATOR = '#\s*[、,，/／]\s*#u';

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
        $heading = $this->firstText($crawler, Selectors::PERFORMER_NAME);
        $externalId = Site::performerId($request->url);
        if ($heading === null || $externalId === null) {
            throw new CrawlParseException(Site::NAME . ' performer detail did not contain a performer heading.');
        }

        $names = $fields->names($heading);
        $profile = $fields->rows($crawler, Selectors::PERFORMER_STATS) + $fields->rows($crawler, Selectors::PERFORMER_ROWS);
        $bust = $fields->bust($profile[self::ROW_BUST] ?? '');
        $waist = $fields->centimetres($profile[self::ROW_WAIST] ?? null);
        $hip = $fields->centimetres($profile[self::ROW_HIP] ?? null);
        $birthday = $profile[self::ROW_BIRTHDAY] ?? null;
        $hobby = $profile[self::ROW_HOBBY] ?? null;
        $works = $this->works($crawler, $request->url, $fields);
        $pagination = $fields->pagination($crawler, $request->url);
        $person = $this->person($crawler);

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $names['name'],
            data: [
                'name_japanese' => $names['name'],
                'aliases' => $names['aliases'],
                'birth_date_raw' => $birthday === null ? null : (preg_match('/^[^（(]+/u', $birthday, $match) === 1 ? trim($match[0]) : $birthday),
                'height_raw' => $profile[self::ROW_HEIGHT] ?? null,
                'size_raw' => $fields->size($bust['bust_cm'], $bust['cup'], $waist, $hip),
                'raw_profile' => $profile,
                'metadata' => MovieMeta::filter([
                    'display_name' => $heading,
                    'name_kana' => $this->firstText($crawler, Selectors::PERFORMER_KANA),
                    'job_title' => $person['jobTitle'] ?? null,
                    'description' => $this->firstAttribute($crawler, Selectors::META_DESCRIPTION, 'content'),
                    'fanza_url' => $fields->link($crawler, Selectors::PERFORMER_FANZA, $request->url),
                    'bust_cm' => $bust['bust_cm'],
                    'cup' => $bust['cup'],
                    'waist_cm' => $waist,
                    'hip_cm' => $hip,
                    'height_cm' => $fields->centimetres($profile[self::ROW_HEIGHT] ?? null),
                    'birth_date' => MovieMeta::isoDate($birthday),
                    'age' => $fields->age($birthday),
                    'blood_type' => $profile[self::ROW_BLOOD_TYPE] ?? null,
                    'birthplace' => $profile[self::ROW_BIRTHPLACE] ?? null,
                    'hobby' => $hobby,
                    'hobbies' => $hobby === null ? [] : preg_split(self::HOBBY_SEPARATOR, $hobby, -1, PREG_SPLIT_NO_EMPTY),
                    'images' => array_map(static fn(array $work): array => $work['image'], $works),
                    'works' => array_map(static fn(array $work): array => $work['work'], $works),
                    'works_filters' => $this->filters($crawler, $request->url, $fields),
                    'works_page' => $pagination['current'],
                    'works_last_page' => $pagination['last'],
                    'works_next_url' => $pagination['next_url'],
                    'source_fields' => $profile,
                ]),
            ],
        );

        $this->assertUsablePerformerDetail($item, Site::NAME);

        return $item;
    }

    /**
     * The works of the page in page order, with their cover image entries.
     *
     * @return list<array{work: array<string, mixed>, image: array<string, mixed>}>
     */
    private function works(Crawler $crawler, string $baseUrl, Fields $fields): array
    {
        $flight = $fields->flightWorks($crawler);
        $works = [];
        $crawler->filter(Selectors::WORK)->each(function (Crawler $card) use (&$works, $baseUrl, $fields, $flight): void {
            $url = $fields->link($card, Selectors::WORK_LINK, $baseUrl);
            $contentId = $url === null ? null : Site::contentId($url);
            $thumbnail = $fields->src($card, Selectors::IMAGE, $baseUrl);
            if ($url === null || $contentId === null || $thumbnail === null) {
                return;
            }

            $title = $this->firstText($card, Selectors::WORK_LINK);
            $spans = $this->texts($card, Selectors::WORK_SPANS);
            $data = $flight[$contentId] ?? [];
            $images = is_array($data['imageURL'] ?? null) ? $data['imageURL'] : [];
            $prices = is_array($data['prices'] ?? null) ? $data['prices'] : [];
            $samples = is_array($data['sampleMovieURL'] ?? null) ? array_values($data['sampleMovieURL']) : [];
            $large = is_string($images['large'] ?? null) ? $images['large'] : null;
            $price = array_values(array_filter($spans, static fn(string $text): bool => str_starts_with($text, '¥')))[0] ?? null;

            $works[] = [
                'work' => MovieMeta::filter([
                    'external_id' => $contentId,
                    'title' => $title,
                    'url' => $url,
                    'price' => $price !== null && preg_match('/(\d+)/', $price, $match) === 1 ? (int) $match[1] : null,
                    'list_price' => is_string($prices['list_price'] ?? null) && ctype_digit($prices['list_price']) ? (int) $prices['list_price'] : null,
                    'on_sale' => in_array('セール', $spans, true) ? true : null,
                    'cover_url' => $large ?? Site::largeCover($thumbnail),
                    'thumb_url' => $thumbnail,
                    'list_thumb_url' => is_string($images['list'] ?? null) ? $images['list'] : null,
                    MovieMeta::SAMPLE_VIDEO_URL => is_string($samples[0] ?? null) ? $samples[0] : null,
                ]),
                'image' => $fields->image($thumbnail, $title, $contentId, $large),
            ];
        });

        return $works;
    }

    /** @return list<array{key: string, label: string, url: string}> the all / 2D / VR filters of the works list */
    private function filters(Crawler $crawler, string $baseUrl, Fields $fields): array
    {
        $filters = [];
        $crawler->filter(Selectors::WORKS_FILTERS)->each(function (Crawler $link) use (&$filters, $baseUrl, $fields): void {
            $url = $fields->anchor($link, $baseUrl);
            $label = $this->normalizeText($link->text(''));
            if ($url === null || $label === null) {
                return;
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $filters[] = ['key' => is_string($query['filter'] ?? null) ? $query['filter'] : 'all', 'label' => $label, 'url' => $url];
        });

        return $filters;
    }

    /**
     * The schema.org Person of the page.
     *
     * @return array<mixed>
     */
    private function person(Crawler $crawler): array
    {
        foreach ($crawler->filter(Selectors::JSON_LD)->each(static fn(Crawler $node): string => $node->text('')) as $json) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['@type'] ?? null) === 'Person') {
                return $data;
            }
        }

        return [];
    }
}
