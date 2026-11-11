<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Hiyoko\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Hiyoko\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
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
        $dom = $this->fetchCrawler($request);
        $id = strtoupper((string) preg_replace('#^.*/(piyo-\d+)/?$#i', '$1', (string) parse_url($request->url, PHP_URL_PATH)));
        $title = $this->firstText($dom, '#second-block-in03 > h2.tit-01');
        $rows = $this->sourceFields($dom);
        $images = $this->images($dom);
        if ($id === '' || $title === null || $images === []) {
            throw new CrawlParseException('Hiyoko detail did not contain expected work fields and images.');
        }

        $date = $this->date($rows['発売日'] ?? null);
        $duration = $this->duration($rows['時間'] ?? null);
        $director = $rows['監督'] ?? null;
        $label = $rows['レーベル'] ?? null;
        $item = MovieDto::item(Site::BASE_URL . '/title/' . strtolower($id) . '/', $id, $title, [
            'code' => $id,
            'description' => $this->firstText($dom, '.kobets-txt'),
            'date' => $date,
            'duration' => $duration,
            'cover_url' => $images[0],
            'screenshots' => array_map(
                static fn(string $url, int $position): array => [
                    'url' => $url,
                    'thumbnail_url' => $url,
                    'position' => $position,
                ],
                $images,
                array_keys($images),
            ),
            'metadata' => [
                'maker' => Site::NAME,
                'label' => $label,
                'director' => $director,
                'purchase_links' => $this->purchaseLinks($dom),
                'source_fields' => $rows,
            ],
        ]);
        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /** @return array<string, string> */
    private function sourceFields(Crawler $dom): array
    {
        $fields = [];
        foreach ($dom->filter('.kobetsu-right .dl-04 dt') as $element) {
            $label = trim((new Crawler($element))->text(''));
            $value = (new Crawler($element))->nextAll()->first();
            if ($label === '' || $value->count() === 0) {
                continue;
            }

            $fields[$label] = trim($value->text(''));
        }

        return $fields;
    }

    /** @return list<string> */
    private function images(Crawler $dom): array
    {
        $images = [];
        foreach ($dom->filter('.kobetsu-left .list-08 a.pop[href]') as $element) {
            $link = new Crawler($element);
            $url = $this->absolute(Site::BASE_URL, (string) $link->attr('href'));
            if (! in_array($url, $images, true)) {
                $images[] = $url;
            }
        }

        return $images;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/u', $value, $match) !== 1) {
            return null;
        }

        return checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            ? sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3])
            : null;
    }

    private function duration(?string $value): ?int
    {
        return $value !== null && preg_match('/(\d+)\s*分/u', $value, $match) === 1 ? (int) $match[1] : null;
    }

    /** @return list<array{name: string, url: string}> */
    private function purchaseLinks(Crawler $dom): array
    {
        $links = [];
        foreach ($dom->filter('.sell-block a[href]') as $element) {
            $link = new Crawler($element);
            $name = trim($link->text(''));
            $href = (string) $link->attr('href');
            if ($name !== '' && $href !== '') {
                $links[] = ['name' => $name, 'url' => $href];
            }
        }

        return $links;
    }
}
