<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Attempt;

use JOOservices\CrawlerX\Enums\FetchMethod;

/**
 * Builds one diagnostic row of the fetch attempt log.
 */
final class AttemptRow
{
    /**
     * @param array<string, list<string>> $headers
     * @return array{started_at: string, duration_ms: int, http_status: ?int, cf_mitigated: ?string, outcome: string, error_code: ?string, method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}
     */
    public static function build(
        FetchMethod $method,
        int $elapsedMs,
        int $status,
        bool $challenge,
        bool $ok,
        ?string $error,
        ?int $started = null,
        array $headers = [],
        bool $skipped = false,
    ): array {
        $cfMitigated = null;
        foreach ($headers as $name => $values) {
            if (strcasecmp($name, 'cf-mitigated') === 0) {
                $cfMitigated = substr($values[0] ?? '', 0, 255);
            }
        }
        $started ??= (int) round(microtime(true) * 1000);
        $row = [
            'started_at' => gmdate('Y-m-d\\TH:i:s', intdiv($started, 1000)) . sprintf('.%03dZ', $started % 1000),
            'duration_ms' => max(0, (int) round(microtime(true) * 1000) - $started),
            'http_status' => $status >= 100 && $status <= 599 ? $status : null,
            'cf_mitigated' => $cfMitigated,
            'outcome' => $skipped ? 'skipped' : ($ok ? 'ok' : 'error'),
            'error_code' => $ok ? null : ($skipped ? 'skipped' : ($challenge ? 'challenge' : ($status >= 400 ? 'http_' . $status : 'fetch_error'))),
            'error' => null,
            'method' => $method->value,
            'elapsed_ms' => $elapsedMs,
            'status' => $status,
            'challenge' => $challenge,
            'ok' => $ok,
        ];

        $error ??= $challenge ? 'challenge detected' : ($status >= 400 ? 'HTTP ' . $status : null);
        if ($error !== null && $error !== '') {
            $redacted = preg_replace('~https?://[^\\s<>]+~i', '[url]', (str_contains($error, '<') ? 'HTML error omitted' : $error)) ?? 'fetch error';
            $row['error'] = preg_match('/\\A.{0,500}/us', $redacted, $matches) === 1 ? $matches[0] : 'fetch error';
        }

        return $row;
    }
}
