<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Commons\CommonsApi;
use JOOservices\CrawlerX\Adapters\Commons\Galleries;
use JOOservices\CrawlerX\Adapters\Commons\Photos;
use JOOservices\CrawlerX\Adapters\Commons\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * A person's gallery (one category, paged by `cmcontinue`) or one file page. A category page needs two API
 * requests (category + ordered file list, then the file data); a file page needs one.
 */
final class Gallery extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $target = Site::target($request->url);
        if ($target === null) {
            throw new CrawlParseException('Commons gallery needs a Category: or File: URL.');
        }

        $item = Site::isCategory($target['title'])
            ? $this->category($target, max(1, $request->page))
            : $this->file($target['title']);

        if ($item === null) {
            throw new CrawlParseException('Commons gallery did not contain the expected fields.');
        }

        return $item;
    }

    /**
     * @param  array{title: string, continue: ?string, limit: int}  $target
     */
    private function category(array $target, int $page): ?CrawlItemResultDto
    {
        $payload = CommonsApi::query(
            $this->client,
            Site::categoryUrl($target['title'], 'file', $target['continue'], $target['limit']),
            'gallery',
        );
        $members = CommonsApi::members($payload);
        $category = array_values(CommonsApi::pages($payload))[0] ?? null;
        if ($category === null) {
            return null;
        }

        $pages = $members === []
            ? []
            : CommonsApi::pages(CommonsApi::query(
                $this->client,
                Site::pagesUrl(CommonsApi::pageIds($members)),
                'gallery files',
            ));

        $photos = [];
        $offset = ($page - 1) * $target['limit'];
        foreach ($members as $member) {
            $photo = isset($pages[$member['pageid']]) ? Photos::fromPage($pages[$member['pageid']], $offset + count($photos) + 1) : null;
            if ($photo instanceof PhotoDto) {
                $photos[] = $photo;
            }
        }

        $token = CommonsApi::continuation($payload);

        return Galleries::fromCategory(
            $category,
            $photos,
            $token === null ? null : Site::membersUrl($target['title'], 'file', $token, $target['limit']),
        );
    }

    private function file(string $title): ?CrawlItemResultDto
    {
        $payload = CommonsApi::query($this->client, Site::fileUrl($title), 'file');
        $page = array_values(CommonsApi::pages($payload))[0] ?? null;

        return $page === null ? null : Galleries::fromFile($page);
    }
}
