<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Bomb\Concerns;

use Psr\Http\Message\ResponseInterface;

/**
 * BOMB.tv serves Shift_JIS pages. The HTML5 parser's own Shift_JIS decoder garbles some kanji (such as 菫 or 眩),
 * so the body is converted to UTF-8 first; the declared charset is then rewritten by {@see \JOOservices\CrawlerX\Adapters\AbstractHtmlType}.
 *
 * @phpstan-require-extends \JOOservices\CrawlerX\Adapters\AbstractHtmlType
 */
trait DecodesShiftJis
{
    protected function htmlFromResponse(ResponseInterface $response, string $site, string $context): string
    {
        $html = parent::htmlFromResponse($response, $site, $context);

        return mb_check_encoding($html, 'UTF-8') ? $html : mb_convert_encoding($html, 'UTF-8', 'CP932');
    }
}
