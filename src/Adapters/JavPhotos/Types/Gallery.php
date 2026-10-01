<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavPhotos\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavPhotos\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Gallery extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'javphotos';
    }

    protected function contextLabel(): string
    {
        return 'gallery';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $externalId = $this->externalId($request->url);
        $title = $this->pageTitle($crawler) ?? $externalId;
        $photos = $this->photos($crawler, $request->url);

        if ($title === null || $photos === []) {
            throw new CrawlParseException('JavPhotos gallery page did not contain expected gallery fields.');
        }

        $footerLinks = $this->footerLinks($crawler);

        $item = GalleryDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'photo_count' => count($photos),
                'performers' => $footerLinks['performers'],
                'tags' => $footerLinks['tags'],
                'photos' => $photos,
                'metadata' => [
                    'movie_code' => $this->movieCode($photos),
                ],
            ],
        );

        return $item;
    }

    /**
     * @return list<PhotoDto>
     */
    private function photos(Crawler $crawler, string $baseUrl): array
    {
        if ($crawler->filter(Selectors::GALLERY_PHOTOS)->count() === 0) {
            return [];
        }

        $photos = [];
        $crawler->filter(Selectors::GALLERY_PHOTOS)->each(function (Crawler $node) use (&$photos, $baseUrl): void {
            $full = $node->attr('href');
            $thumbnail = $node->filter('img')->count() > 0
                ? $node->filter('img')->first()->attr('src')
                : null;

            $photos[] = new PhotoDto(
                url: $full === null ? null : $this->absolute($baseUrl, $full),
                imageUrl: $full === null ? null : $this->absolute($baseUrl, $full),
                thumbnailUrl: $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
                position: count($photos) + 1,
            );
        });

        return $photos;
    }

    private function pageTitle(Crawler $crawler): ?string
    {
        $title = $this->firstText($crawler, 'title');
        if ($title === null) {
            return null;
        }

        $title = (string) preg_replace('/^Jav Photos Free\s+/i', '', $title);
        $title = (string) preg_replace('/\s+HD Porn Pics Gallery$/i', '', $title);
        $title = trim($title);

        return $title !== '' ? $title : null;
    }

    /**
     * @return array{performers: list<string>, tags: list<string>}
     */
    private function footerLinks(Crawler $crawler): array
    {
        $labels = [];
        $crawler->filter(Selectors::GALLERY_FOOTER_LINKS)->each(function (Crawler $node) use (&$labels): void {
            $label = $this->normalizeText($node->text(''));
            if ($label !== null) {
                $labels[] = $label;
            }
        });

        $performers = array_values(array_filter($labels, fn(string $label): bool => preg_match('/^[A-Za-z][A-Za-z -]+$/u', $label) === 1));

        return [
            'performers' => array_slice($performers, 0, 3),
            'tags' => array_values(array_diff($labels, $performers)),
        ];
    }

    /**
     * @param  list<PhotoDto>  $photos
     */
    private function movieCode(array $photos): ?string
    {
        foreach ($photos as $photo) {
            $url = $photo->imageUrl ?? $photo->url;
            if (! is_string($url)) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path) && preg_match('#/([A-Za-z0-9_]+)/[^/]+\.jpe?g$#i', $path, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/free/([^/]+)/?$#i', $path, $match) === 1) {
            $slug = $match[1];

            return ! is_numeric($slug) ? $slug : null;
        }

        return null;
    }
}
