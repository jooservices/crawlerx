<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Javbtc\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Javbtc\Selectors;
use JOOservices\CrawlerX\Adapters\Javbtc\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Javbtc', 'detail');

        return $this->parse($request->url, $html);
    }

    private function parse(string $url, string $html): CrawlItemResultDto
    {
        $crawler = new Crawler($html, $url);
        $externalId = $this->externalId($url);
        $codeRaw = $this->productCode($crawler);
        $code = CodeNormalizer::canonical($codeRaw, $externalId, $url);
        $title = $this->title($crawler, $codeRaw) ?? $code ?? $externalId;
        $streamUrl = $this->streamUrl($crawler, $url);
        $coverUrl = $this->coverUrl($crawler, $url);
        $clipLabel = $this->clipDurationLabel($crawler, $url);

        $item = MovieDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [
                'code' => $code,
                'cover_url' => $coverUrl,
                'description' => $this->description($crawler, $codeRaw),
                'performers' => $this->performerEntries($crawler, $url),
                'duration' => MovieMeta::durationMinutes($clipLabel),
                'maker' => $this->breadcrumbLink($crawler, 'fa-video'),
                'maker_url' => $this->breadcrumbEntry($crawler, 'fa-video', $url)['url'] ?? null,
                'sample_video_url' => $streamUrl,
                'tags' => $this->tags($crawler),
                'download_url' => $streamUrl,
                'downloads' => $streamUrl === null ? [] : [[
                    'type' => 'direct',
                    'url' => $streamUrl,
                    'quality' => 'preview',
                    'metadata' => ['source' => 'stream'],
                ]],
                'metadata' => array_filter([
                    'product_code_raw' => $codeRaw,
                    'cdn_mirror' => $this->cdnMirror($streamUrl ?? $coverUrl),
                    'clip_duration_label' => $clipLabel,
                ], fn(mixed $value): bool => $value !== null && $value !== ''),
            ],
        );

        $this->assertUsableDetail($item, 'Javbtc');

        return $item;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim(basename($path), '/') : null;
    }

    private function productCode(Crawler $crawler): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_BREADCRUMB)->count() === 0) {
            return null;
        }

        $breadcrumb = $crawler->filter(Selectors::DETAIL_BREADCRUMB)->first();
        $codeLink = $breadcrumb->filter('a[href="/"], a[href="/download/"], a[href^="/download/"]');
        if ($codeLink->count() > 0) {
            $text = $this->normalizeText(preg_replace('/^.*fa-cloud-download-alt\s*/i', '', $codeLink->first()->text('')) ?? '');
            if ($text !== null) {
                return $text;
            }
        }

        if ($crawler->filter(Selectors::DETAIL_DESCRIPTION)->count() > 0) {
            $href = $crawler->filter(Selectors::DETAIL_DESCRIPTION)->first()->attr('href');
            if (is_string($href) && preg_match('#/download/([^/]+)#', $href, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private function title(Crawler $crawler, ?string $codeRaw): ?string
    {
        $fromDescription = $this->description($crawler, $codeRaw);
        if ($fromDescription !== null) {
            return $fromDescription;
        }

        $meta = $this->firstAttribute($crawler, Selectors::DETAIL_META_DESCRIPTION, 'content');
        if ($meta === null) {
            return null;
        }

        if (preg_match('/!\s*(.+?)\.\s+[a-z0-9-]+\s/i', $meta, $match) === 1) {
            return $this->normalizeText(rtrim($match[1], '.'));
        }

        if (preg_match('/!\s*(.+)$/i', $meta, $match) === 1) {
            return $this->normalizeText($match[1]);
        }

        return $this->normalizeText($meta);
    }

    private function description(Crawler $crawler, ?string $codeRaw): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_DESCRIPTION)->count() === 0) {
            return null;
        }

        $text = $this->normalizeText($crawler->filter(Selectors::DETAIL_DESCRIPTION)->first()->text(''));
        if ($text === null) {
            return null;
        }

        if ($codeRaw !== null) {
            $stripped = preg_replace('/^' . preg_quote($codeRaw, '/') . '\s*/i', '', $text);

            return is_string($stripped) ? $this->normalizeText($stripped) : $text;
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function tags(Crawler $crawler): array
    {
        $tags = [];

        $crawler->filter(Selectors::DETAIL_TAG_BLOCK)->each(function (Crawler $node) use (&$tags): void {
            if ($node->filter('a .fa-tag')->count() === 0) {
                return;
            }

            $node->filter('a')->each(function (Crawler $link) use (&$tags): void {
                if ($link->filter('.fa-tag')->count() === 0) {
                    return;
                }

                $label = $this->normalizeText($link->text(''));
                if ($label !== null) {
                    $tags[] = $label;
                }
            });
        });

        return array_values(array_unique($tags));
    }

    /**
     * @return list<string>
     */
    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    private function performerEntries(Crawler $crawler, string $baseUrl): array
    {
        $entry = $this->breadcrumbEntry($crawler, 'fa-female', $baseUrl);

        return $entry === null ? [] : [MovieMeta::performer($entry['name'], $this->slug($entry['url']), $entry['url'])];
    }

    private function slug(?string $url): ?string
    {
        $slug = basename((string) parse_url((string) $url, PHP_URL_PATH));

        return $slug === '' ? null : $slug;
    }

    private function breadcrumbLink(Crawler $crawler, string $iconClass): ?string
    {
        return $this->breadcrumbEntry($crawler, $iconClass, '')['name'] ?? null;
    }

    /** @return array{name: string, url: ?string}|null */
    private function breadcrumbEntry(Crawler $crawler, string $iconClass, string $baseUrl): ?array
    {
        if ($crawler->filter(Selectors::DETAIL_BREADCRUMB)->count() === 0) {
            return null;
        }

        $entry = null;
        $crawler->filter(Selectors::DETAIL_BREADCRUMB)->first()->filter('a')->each(function (Crawler $node) use (&$entry, $iconClass, $baseUrl): void {
            if ($entry !== null || $node->filter('.' . $iconClass)->count() === 0) {
                return;
            }

            $name = $this->normalizeText($node->text(''));
            $href = $node->attr('href');
            if ($name !== null) {
                $entry = ['name' => $name, 'url' => is_string($href) && $href !== '' && $baseUrl !== '' ? $this->normalizer->absolute($baseUrl, $href) : null];
            }
        });

        return $entry;
    }

    private function coverUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $poster = $this->firstAttribute($crawler, Selectors::DETAIL_VIDEO, 'poster');
        if ($poster !== null) {
            return $this->normalizer->absolute($baseUrl, $poster);
        }

        return null;
    }

    private function streamUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $source = $this->firstAttribute($crawler, Selectors::DETAIL_VIDEO . ' source', 'src')
            ?? $this->firstAttribute($crawler, Selectors::DETAIL_VIDEO, 'src');

        return $source === null ? null : $this->normalizer->absolute($baseUrl, $source);
    }

    private function clipDurationLabel(Crawler $crawler, string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $matched = null;
        $crawler->filter('div.a4 > a[href]')->each(function (Crawler $node) use (&$matched, $path): void {
            if ($matched !== null) {
                return;
            }

            $href = $node->attr('href');
            if (! is_string($href) || $href !== $path) {
                return;
            }

            if ($node->filter('b')->count() === 0) {
                return;
            }

            $text = $this->normalizeText($node->filter('b')->first()->text(''));
            if ($text !== null && preg_match('/^\d{1,2}:\d{2}$/', $text) === 1) {
                $matched = $text;
            }
        });

        return $matched;
    }

    private function cdnMirror(?string $url): ?string
    {
        if ($url === null || preg_match('#/movie/([^/]+)/#', $url, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
