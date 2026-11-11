<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Silklabo;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

final class Fields
{
    use ParsesHtml;

    /** @return array<string, string> */
    public function labelledFields(Crawler $scope): array
    {
        $text = $this->normalizeText($scope->text(''));
        if ($text === null) {
            return [];
        }

        preg_match_all('/【([^】]+)】([^【]*)/u', $text, $matches, PREG_SET_ORDER);
        $fields = [];
        foreach ($matches as $match) {
            $value = $this->normalizeText($match[2]);
            if ($value !== null) {
                $fields[$this->normalizeText($match[1]) ?? $match[1]] = $value;
            }
        }

        return $fields;
    }
}
