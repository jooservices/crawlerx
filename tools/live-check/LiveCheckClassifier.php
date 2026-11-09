<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use Psr\Http\Client\NetworkExceptionInterface;
use Throwable;

final class LiveCheckClassifier
{
    /** Classifies observed crawl outcomes and transport failures.
     *
     * @return array{status: string, item_count: int, error_code: ?string}
     */
    public function classify(CrawlOutcomeDto|Throwable $outcome): array
    {
        if ($outcome instanceof Throwable) {
            $isNetworkFailure = $outcome instanceof NetworkExceptionInterface;

            return [
                'status' => $isNetworkFailure ? 'network' : 'parse-error',
                'item_count' => 0,
                'error_code' => $isNetworkFailure ? 'network' : 'unexpected_error',
            ];
        }

        if (! $outcome->ok) {
            $code = $outcome->error?->code;

            return [
                'status' => $this->failureStatus($code),
                'item_count' => 0,
                'error_code' => $code?->value,
            ];
        }

        if ($outcome->list !== null) {
            $count = count($outcome->list->items);

            return ['status' => $count > 0 ? 'ok' : 'empty', 'item_count' => $count, 'error_code' => null];
        }

        if ($outcome->item !== null) {
            $hasIdentity = $this->itemHasIdentity($outcome->item);

            return ['status' => $hasIdentity ? 'ok' : 'empty', 'item_count' => $hasIdentity ? 1 : 0, 'error_code' => null];
        }

        return ['status' => 'parse-error', 'item_count' => 0, 'error_code' => 'missing_result'];
    }

    /** Maps crawler errors into the live report's health categories. */
    private function failureStatus(?CrawlErrorCode $code): string
    {
        return match ($code) {
            CrawlErrorCode::Blocked,
            CrawlErrorCode::Challenge,
            CrawlErrorCode::RateLimited,
            CrawlErrorCode::AuthRequired,
            CrawlErrorCode::SsrfBlocked => 'blocked',
            CrawlErrorCode::ParseFailed,
            CrawlErrorCode::UnsupportedUrl,
            CrawlErrorCode::AmbiguousUrl,
            CrawlErrorCode::AdapterNotFound,
            CrawlErrorCode::Unknown => 'parse-error',
            CrawlErrorCode::NotFound,
            CrawlErrorCode::Gone => 'empty',
            CrawlErrorCode::Network,
            CrawlErrorCode::Timeout => 'network',
            default => 'parse-error',
        };
    }

    /** Checks whether a parsed detail result contains an identifying value. */
    private function itemHasIdentity(CrawlItemResultDto $item): bool
    {
        $entity = $item->meta[$item->entityType] ?? null;
        if (! is_array($entity)) {
            return false;
        }

        foreach (['external_id', 'title', 'name'] as $key) {
            if (is_string($entity[$key] ?? null) && trim($entity[$key]) !== '') {
                return true;
            }
        }

        return false;
    }
}
