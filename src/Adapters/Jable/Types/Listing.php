<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jable\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Jable\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'Jable';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request): void {
            if ($card->filter(Selectors::LISTING_LINKS)->count() === 0) {
                return;
            }

            $link = $card->filter(Selectors::LISTING_LINKS)->first();
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $externalId = $this->externalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $rawTitle = $this->listingTitle($card, $link);
            $code = CodeNormalizer::canonical($rawTitle, $externalId, $url) ?? strtoupper($externalId);
            $metadata = $this->listingMetadata($card);

            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $this->titleWithoutCode($rawTitle, $code),
                data: [
                    'code' => $code,
                    'cover_url' => $this->listingCover($card, $request->url),
                    'duration' => $this->durationMinutes($card),
                    'metadata' => $metadata,
                ],
            );
        });

        if ($items === []) {
            throw new \RuntimeException('Jable listing page did not contain expected movie fields.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->parsePagination($crawler, $request->url, $request->page),
        );
    }

    private function parsePagination(Crawler $crawler, string $url, int $currentPage): CrawlPaginationDto
    {
        $pageLinks = [];
        $explicitNextUrl = null;
        $lastPageUrl = null;

        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $node) use (
            &$pageLinks,
            &$explicitNextUrl,
            &$lastPageUrl,
            $url,
        ): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $absolute = $this->absolute($url, $href);
            $label = strtolower(trim($node->text('')));
            $aria = strtolower(trim((string) $node->attr('aria-label')));
            $rel = strtolower(trim((string) $node->attr('rel')));
            $page = $this->pageFromUrl($absolute, $url);

            if ($rel === 'next' || $aria === 'next page' || $label === 'next' || $label === '›' || $label === '>') {
                $explicitNextUrl = $absolute;
            }

            if ($aria === 'last page' || $label === '»') {
                $lastPageUrl = $absolute;
            }

            if ($page !== null) {
                $pageLinks[$page] = $absolute;
            }
        });

        $lastPage = $lastPageUrl !== null ? $this->pageFromUrl($lastPageUrl, $url) : null;
        $visiblePages = array_keys($pageLinks);
        if ($lastPage === null && $visiblePages !== []) {
            $lastPage = max([$currentPage, ...$visiblePages]);
        }

        $nextPage = null;
        $nextUrl = null;

        if ($explicitNextUrl !== null) {
            $nextPage = $this->pageFromUrl($explicitNextUrl, $url) ?? $currentPage + 1;
            $nextUrl = $explicitNextUrl;
        } else {
            $greaterPages = array_values(array_filter($visiblePages, fn(int $page): bool => $page > $currentPage));
            sort($greaterPages);
            $nextPage = $greaterPages[0] ?? null;
            $nextUrl = $nextPage === null ? null : ($pageLinks[$nextPage] ?? null);
        }

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $lastPage,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null && $currentPage < ($lastPage ?? $nextPage),
        );
    }

    private function listingTitle(Crawler $card, Crawler $link): ?string
    {
        if ($card->filter(Selectors::LISTING_TITLE)->count() > 0) {
            return $this->normalizeText($card->filter(Selectors::LISTING_TITLE)->first()->text(''));
        }

        $title = $link->attr('title');

        return is_string($title) && trim($title) !== '' ? $this->normalizeText($title) : $this->normalizeText($link->text(''));
    }

    private function listingCover(Crawler $card, string $baseUrl): ?string
    {
        if ($card->filter(Selectors::LISTING_COVER)->count() === 0) {
            return null;
        }

        $img = $card->filter(Selectors::LISTING_COVER)->first();
        $src = $img->attr('data-src') ?? $img->attr('src');

        return is_string($src) && trim($src) !== ''
            ? $this->absolute($baseUrl, $src)
            : null;
    }

    /**
     * @return array{views: ?int, likes: ?int}
     */
    private function parseCounterStats(string $text): array
    {
        $tokens = preg_split('/\s+/', trim($text));
        $tokens = $tokens === false ? [] : $tokens;
        $numeric = array_values(array_filter(
            $tokens,
            fn(string $token): bool => preg_match('/^\d[\d,]*$/', $token) === 1,
        ));

        if ($numeric === []) {
            return ['views' => null, 'likes' => null];
        }

        if (count($numeric) === 1) {
            return [
                'views' => $this->integerStat($numeric[0]),
                'likes' => null,
            ];
        }

        $likes = $this->integerStat(array_pop($numeric));
        $views = $this->integerStat(implode('', $numeric));

        return ['views' => $views, 'likes' => $likes];
    }

    /**
     * @return array<string, mixed>
     */
    private function listingMetadata(Crawler $card): array
    {
        $metadata = [];

        if ($card->filter(Selectors::LISTING_STATS)->count() === 0) {
            return $metadata;
        }

        $text = $this->normalizeText($card->filter(Selectors::LISTING_STATS)->first()->text('')) ?? '';
        $stats = $this->parseCounterStats($text);

        if ($stats['views'] !== null) {
            $metadata['views'] = $stats['views'];
        }

        if ($stats['likes'] !== null) {
            $metadata['likes'] = $stats['likes'];
        }

        $preview = $card->filter('img[data-preview]')->count() > 0
            ? $card->filter('img[data-preview]')->first()->attr('data-preview')
            : null;
        if (is_string($preview) && trim($preview) !== '') {
            $metadata['preview_url'] = $preview;
        }

        return $metadata;
    }

    private function durationMinutes(Crawler $card): ?int
    {
        if ($card->filter(Selectors::LISTING_DURATION)->count() === 0) {
            return null;
        }

        $value = trim($card->filter(Selectors::LISTING_DURATION)->first()->text(''));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $value, $matches) === 1) {
            $hours = $matches[1] === '' ? 0 : (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];

            return max(1, ($hours * 60) + $minutes + (int) round($seconds / 60));
        }

        if (preg_match('/^(\d+):(\d+)$/', $value, $matches) === 1) {
            return max(1, (int) $matches[1] + (int) round(((int) $matches[2]) / 60));
        }

        return null;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_contains($path, '/videos/')) {
            return null;
        }

        $basename = trim(basename($path), '/');

        return $basename !== '' ? $basename : null;
    }

    private function titleWithoutCode(?string $title, string $code): ?string
    {
        if ($title === null) {
            return null;
        }

        $stripped = preg_replace('/^' . preg_quote($code, '/') . '\s*/i', '', $title) ?? $title;

        return trim($stripped) !== '' ? trim($stripped) : $title;
    }

    private function pageFromUrl(string $url, string $listingUrl): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        $listingPath = parse_url($listingUrl, PHP_URL_PATH);
        if (! is_string($path) || ! is_string($listingPath)) {
            return null;
        }

        // $listingUrl is the URL being crawled, which already carries its own
        // page segment (e.g. /new-release/2/). Strip it so sibling page links
        // (e.g. /new-release/3/) resolve against the listing root, not against
        // the current page as if it were the root.
        $root = preg_replace('#/\d+$#', '', trim($listingPath, '/')) ?? trim($listingPath, '/');
        if ($root === '') {
            return null;
        }

        $normalizedPath = trim($path, '/');
        if (strcasecmp($normalizedPath, $root) === 0) {
            return 1;
        }

        $pattern = '#^' . preg_quote($root, '#') . '/(\d+)$#i';
        if (preg_match($pattern, $normalizedPath, $matches) !== 1) {
            return null;
        }

        $page = (int) $matches[1];

        return $page > 0 ? $page : null;
    }

    private function integerStat(string $value): int
    {
        $normalized = str_replace([',', ' '], '', $value);

        return is_numeric($normalized) ? (int) $normalized : 0;
    }
}
