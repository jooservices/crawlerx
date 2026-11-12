<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The labelled facts of a TMA product page. The site has no dt/th table: the publisher and type are `label: value`
 * lines, and the release date, code, prices, runtime and JAN code sit in one paragraph of the description.
 */
final class ProductFields
{
    use ParsesHtml;

    private const FACT_PATTERNS = [
        '発売日' => '/[0-9]{4}年[0-9]{1,2}月[0-9]{1,2}日.{0,12}?発売/u',
        '品番' => '/[A-Za-z0-9]+-[0-9]+/',
        '価格' => '/￥[0-9,]+\(税込\)\s*￥[0-9,]+\(税抜\)/u',
        '収録時間' => '/[0-9]+分/u',
        'JANコード' => '/JANコード[:：]\s*([0-9]+)/u',
    ];

    /** @var array<string, string> label => raw value, in page order */
    private readonly array $rows;

    public function __construct(Crawler $dom)
    {
        $this->rows = $this->readRows($dom);
    }

    /** @return array<string, string> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function date(): ?string
    {
        return MovieMeta::isoDate($this->rows['発売日'] ?? null);
    }

    public function duration(): ?int
    {
        return MovieMeta::durationMinutes($this->rows['収録時間'] ?? null);
    }

    public function code(): ?string
    {
        return $this->rows['品番'] ?? null;
    }

    public function maker(): ?string
    {
        return $this->rows['発売元'] ?? null;
    }

    public function type(): ?string
    {
        return $this->rows['タイプ'] ?? null;
    }

    public function janCode(): ?string
    {
        return $this->rows['JANコード'] ?? null;
    }

    public function priceIncludingTax(): ?int
    {
        return $this->priceAmount('税込');
    }

    public function priceExcludingTax(): ?int
    {
        return $this->priceAmount('税抜');
    }

    /** @return array<string, string> */
    private function readRows(Crawler $dom): array
    {
        $rows = [];
        foreach (['.product-classification .vendor', '.morelinks .contentsection'] as $selector) {
            $line = $this->firstText($dom, $selector);
            if ($line !== null && preg_match('/^(.+?)\s*[:：]\s*(.+)$/u', $line, $match) === 1) {
                $rows[trim($match[1])] = trim($match[2]);
            }
        }

        $facts = $this->factsParagraph($dom);
        foreach (self::FACT_PATTERNS as $label => $pattern) {
            $value = $this->fact($facts, $pattern);
            if ($value !== null) {
                $rows[$label] = $value;
            }
        }

        return $rows;
    }

    /** The description paragraph that carries the release line (the synopsis paragraph does not). */
    private function factsParagraph(Crawler $dom): string
    {
        foreach ($dom->filter('.description p') as $element) {
            $text = (new Crawler($element))->text('');
            if (str_contains($text, '発売')) {
                return $text;
            }
        }

        return '';
    }

    private function fact(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $match) !== 1) {
            return null;
        }

        return trim($match[1] ?? $match[0]);
    }

    private function priceAmount(string $label): ?int
    {
        $price = $this->rows['価格'] ?? '';
        if (preg_match('/￥([0-9,]+)\(' . $label . '\)/u', $price, $match) !== 1) {
            return null;
        }

        return (int) str_replace(',', '', $match[1]);
    }
}
