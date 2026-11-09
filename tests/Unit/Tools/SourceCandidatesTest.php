<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;

final class SourceCandidatesTest extends TestCase
{
    public function test_replacement_candidates_record_ordinary_robots_rules_and_delays(): void
    {
        $rows = $this->candidateRows();
        $replacementHosts = [
            'hot-et.com',
            'fairway-av.com',
            'officevish.com',
            'www.naturalhigh.co.jp',
            'www.gentosha.co.jp',
            'www.tokuma.jp',
            'gravidia.jp',
        ];

        foreach ($replacementHosts as $host) {
            self::assertArrayHasKey($host, $rows);
            $robots = $rows[$host]['catalog'];

            self::assertStringContainsString('robots.txt', $robots, $host);
            self::assertGreaterThanOrEqual(3, substr_count($robots, '`200`'), $host);
            self::assertMatchesRegularExpression('/User-[Aa]gent: \*/', $robots, $host);
            self::assertTrue(
                str_contains($robots, 'allow') || str_contains($robots, 'disallow'),
                sprintf('%s must summarize applicable allow/disallow paths.', $host),
            );
            self::assertStringContainsString('Crawl-delay', $robots, $host);
            self::assertStringContainsString('AI-specific disallow', $robots, $host);
        }
    }

    public function test_each_counted_candidate_records_robots_list_and_detail_statuses(): void
    {
        $rows = $this->candidateRows();

        self::assertCount(15, $rows);

        foreach ($rows as $host => $row) {
            self::assertGreaterThanOrEqual(3, substr_count($row['catalog'], '`200`'), $host);
        }
    }

    /**
     * @return array<string, array{catalog: string, images: string, fields: string}>
     */
    private function candidateRows(): array
    {
        $path = dirname(__DIR__, 3) . '/docs/source-candidates.md';
        $document = file_get_contents($path);
        self::assertIsString($document);

        $rows = [];
        foreach (explode("\n", $document) as $line) {
            if (preg_match('/^\| \[[^\]]+\]\([^)]+\) \(`(?<host>[^`]+)`\) \|(?<cells>.*)\|$/', $line, $matches) !== 1) {
                continue;
            }

            $cells = array_map('trim', explode('|', $matches['cells']));
            self::assertCount(3, $cells, $matches['host']);
            $rows[strtolower($matches['host'])] = [
                'catalog' => $cells[0],
                'images' => $cells[1],
                'fields' => $cells[2],
            ];
        }

        return $rows;
    }
}
