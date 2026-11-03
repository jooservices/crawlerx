<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom;

/** Reads a displayed price such as "$6.15" or "$6.15 ~" (currency symbol first). */
final class Price
{
    private const CURRENCIES = ['$' => 'USD', '€' => 'EUR', '£' => 'GBP', '¥' => 'JPY'];

    /** @return array{amount: float, currency: string}|null */
    public static function parse(?string $text): ?array
    {
        if ($text === null || preg_match('/(?<symbol>[$€£¥])\s*(?<amount>\d[\d,]*(?:\.\d+)?)/u', $text, $match) !== 1) {
            return null;
        }

        return ['amount' => (float) str_replace(',', '', $match['amount']), 'currency' => self::CURRENCIES[$match['symbol']]];
    }
}
