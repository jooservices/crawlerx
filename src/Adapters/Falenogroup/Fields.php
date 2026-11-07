<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Falenogroup;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parsing shared by the listing cards and the detail page: the time-gated store buttons and their release note.
 */
final class Fields
{
    use ParsesHtml;

    private const RELEASE_NOTE_MARK = '発売開始';

    /**
     * Store buttons that carry a link (FANZA, H-NEXT, Amazon), with the date the button goes live.
     *
     * @return list<array{store: string, label: ?string, url: string, available_from: ?string}>
     */
    public function purchaseLinks(Crawler $scope, string $selector): array
    {
        $links = [];
        $scope->filter($selector)->each(function (Crawler $node) use (&$links): void {
            $url = trim((string) $node->attr('href'));
            if ($url === '' || isset($links[$url])) {
                return;
            }

            $timer = $node->closest('.view_timer');
            $links[$url] = [
                'store' => $this->store($url),
                'label' => $this->normalizeText($node->text('')),
                'url' => $url,
                'available_from' => $timer === null ? null : MovieMeta::isoDate((string) $timer->attr('data-start-date')),
            ];
        });

        return array_values($links);
    }

    /**
     * The FANZA pre-release button text, for example "2026/12/4 発売開始".
     */
    public function releaseNote(Crawler $scope, string $selector): ?string
    {
        foreach ($this->texts($scope, $selector) as $text) {
            if (str_contains($text, self::RELEASE_NOTE_MARK) && preg_match('/\d/', $text) === 1) {
                return $text;
            }
        }

        return null;
    }

    private function store(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return match (true) {
            str_contains($host, 'dmm.co.jp') => 'fanza',
            str_contains($host, 'hnext.jp') => 'hnext',
            str_contains($host, 'amazon.') => 'amazon',
            default => $host,
        };
    }
}
