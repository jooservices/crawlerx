<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use Closure;
use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use Psr\Http\Client\NetworkExceptionInterface;
use Throwable;

final class LiveCheckReportRunner
{
    /**
     * @param Closure(array{slug: string, adapter_name: string, role: string, name: string, type: \JOOservices\CrawlerX\Enums\CrawlType, url: string, fallback_required?: bool}): CrawlOutcomeDto $crawl
     * @param Closure(int): void $sleepMicroseconds
     * @param Closure(): int $clockNanoseconds
     */
    public function __construct(
        private readonly Closure $crawl,
        private readonly Closure $sleepMicroseconds,
        private readonly Closure $clockNanoseconds,
        private readonly LiveCheckClassifier $classifier = new LiveCheckClassifier(),
    ) {
    }

    /** Runs selected sample crawls and returns their report rows.
     *
     * @param list<array{slug: string, adapter_name: string, role: string, name: string, type: \JOOservices\CrawlerX\Enums\CrawlType, url: string, fallback_required?: bool}> $samples
     * @return list<array{slug: string, adapter_name: string, sample_role: string, sample_name: string, sample_type: string, url: string, status: string, item_count: int, duration_ms: int, error_code: ?string}>
     */
    public function run(array $samples): array
    {
        $results = [];
        $lastRequestByHost = [];
        $requestCountByAdapter = [];

        foreach ($samples as $sample) {
            if (($requestCountByAdapter[$sample['slug']] ?? 0) >= 2) {
                continue;
            }

            $host = strtolower((string) parse_url($sample['url'], PHP_URL_HOST));
            if ($host === '') {
                $classification = ['status' => 'parse-error', 'item_count' => 0, 'error_code' => 'invalid_url'];
                $durationMs = 0;
            } else {
                $this->waitForHost($host, $lastRequestByHost);
                $startedAt = ($this->clockNanoseconds)();
                $lastRequestByHost[$host] = $startedAt;
                $requestCountByAdapter[$sample['slug']] = ($requestCountByAdapter[$sample['slug']] ?? 0) + 1;

                try {
                    $outcome = ($this->crawl)($sample);
                    $classification = $this->classifier->classify($outcome, $sample['fallback_required'] ?? false);
                } catch (LiveCheckRequestLimitException $exception) {
                    $classification = ['status' => 'not-checked', 'item_count' => 0, 'error_code' => $exception->reason];
                } catch (Throwable $exception) {
                    $classification = $exception instanceof NetworkExceptionInterface
                        ? $this->classifier->classify($exception, $sample['fallback_required'] ?? false)
                        : $this->classifier->classify(CrawlOutcomeDto::failure(
                            new CrawlErrorDto(CrawlErrorCode::Unknown, 'Unexpected live-check error.'),
                        ), $sample['fallback_required'] ?? false);
                }

                $durationMs = (int) round((($this->clockNanoseconds)() - $startedAt) / 1_000_000);
            }

            $results[] = [
                'slug' => $sample['slug'],
                'adapter_name' => $sample['adapter_name'],
                'sample_role' => $sample['role'],
                'sample_name' => $sample['name'],
                'sample_type' => $sample['type']->value,
                'url' => $sample['url'],
                'status' => $classification['status'],
                'item_count' => $classification['item_count'],
                'duration_ms' => max(0, $durationMs),
                'error_code' => $classification['error_code'],
            ];
        }

        return $results;
    }

    /** Waits until a hostname's previous sample request is at least ten seconds old.
     *
     * @param array<string, int> $lastRequestByHost
     */
    private function waitForHost(string $host, array $lastRequestByHost): void
    {
        if (! isset($lastRequestByHost[$host])) {
            return;
        }

        $minimumIntervalNanoseconds = 10_000_000_000;
        $elapsed = ($this->clockNanoseconds)() - $lastRequestByHost[$host];
        $remaining = $minimumIntervalNanoseconds - $elapsed;
        if ($remaining > 0) {
            ($this->sleepMicroseconds)((int) ceil($remaining / 1_000));
        }
    }
}
