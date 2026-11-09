<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class LiveCheckReportWriter
{
    private const STATUSES = ['ok', 'empty', 'blocked', 'parse-error', 'network'];

    /**
     * @param list<array{slug: string, adapter_name: string, sample_role: string, sample_name: string, sample_type: string, url: string, status: string, item_count: int, duration_ms: int, error_code: ?string}> $results
     * @return array{json: string, markdown: string}
     */
    public function render(array $results, DateTimeImmutable $generatedAt): array
    {
        $summary = array_fill_keys(self::STATUSES, 0);
        foreach ($results as $result) {
            if (isset($summary[$result['status']])) {
                ++$summary[$result['status']];
            }
        }

        $report = [
            'generated_at' => $generatedAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'adapter_count' => count(array_unique(array_column($results, 'slug'))),
            'check_count' => count($results),
            'summary' => $summary,
            'results' => $results,
        ];

        return [
            'json' => json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
            'markdown' => $this->markdown($report),
        ];
    }

    /**
     * @param list<array{slug: string, adapter_name: string, sample_role: string, sample_name: string, sample_type: string, url: string, status: string, item_count: int, duration_ms: int, error_code: ?string}> $results
     * @return array{json: string, markdown: string, json_path: string, markdown_path: string}
     */
    public function write(array $results, string $directory, DateTimeImmutable $generatedAt): array
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create report directory [{$directory}].");
        }

        $rendered = $this->render($results, $generatedAt);
        $fileStamp = $generatedAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $jsonPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "live-check-{$fileStamp}.json";
        $markdownPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "live-check-{$fileStamp}.md";

        if (file_put_contents($jsonPath, $rendered['json'], LOCK_EX) === false
            || file_put_contents($markdownPath, $rendered['markdown'], LOCK_EX) === false
        ) {
            throw new RuntimeException('Unable to write live-check report files.');
        }

        return [...$rendered, 'json_path' => $jsonPath, 'markdown_path' => $markdownPath];
    }

    /** @param array<string, mixed> $report */
    private function markdown(array $report): string
    {
        /** @var array<string, int> $summary */
        $summary = $report['summary'];
        $lines = [
            '# Live adapter health report',
            '',
            '- Generated: ' . $report['generated_at'],
            '- Adapters checked: ' . $report['adapter_count'],
            '- Samples checked: ' . $report['check_count'],
            '- Results: ' . implode(', ', array_map(
                static fn(string $status): string => "{$status} {$summary[$status]}",
                self::STATUSES,
            )),
            '',
            '| Adapter | Sample | Type | Status | Items | Duration | URL |',
            '| --- | --- | --- | --- | ---: | ---: | --- |',
        ];

        foreach ($report['results'] as $result) {
            $lines[] = sprintf(
                '| %s (`%s`) | %s (`%s`) | %s | %s%s | %d | %.2fs | %s |',
                $this->escapeCell($result['adapter_name']),
                $this->escapeCell($result['slug']),
                $this->escapeCell($result['sample_role']),
                $this->escapeCell($result['sample_name']),
                $this->escapeCell($result['sample_type']),
                $this->escapeCell($result['status']),
                $result['error_code'] === null ? '' : ' (' . $this->escapeCell($result['error_code']) . ')',
                $result['item_count'],
                $result['duration_ms'] / 1_000,
                $this->escapeCell($result['url']),
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function escapeCell(string $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $value);
    }
}
